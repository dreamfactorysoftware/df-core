<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Components\Builder as DfBuilder;
use DreamFactory\Core\Components\Cacheable;
use DreamFactory\Core\Components\SchemaToOpenApiDefinition;
use DreamFactory\Core\Contracts\CacheInterface;
use DreamFactory\Core\Database\ConnectionExtension;
use DreamFactory\Core\Database\Schema\RelationSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\DataFormatter;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session as SessionUtility;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Scalar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Crypt;
use DB;
use SystemTableModelMapper;

/**
 * Class BaseModel
 *
 * @package DreamFactory\Core\Models
 */
class BaseModel extends Model implements CacheInterface
{
    use Cacheable, ConnectionExtension, SchemaToOpenApiDefinition;

    /**
     * Mask to return when visible, but masked, attributes are returned from toArray()
     *
     * @var string
     */
    const PROTECTION_MASK = '**********';

    /**
     * The attributes that should be visible, but masked for protection.
     * Set this to false to use internally to expose passwords, etc., i.e. unprotected.
     *
     * @var boolean
     */
    public $protectedView = true;

    /**
     * TableSchema
     *
     * @var TableSchema
     */
    protected $tableSchema = null;

    /**
     * Array of table references from TableSchema
     *
     * @var array
     */
    protected $references = [];

    /**
     * The attributes that should be encrypted on write, decrypted on read.
     *
     * @var array
     */
    protected $encrypted = [];

    /**
     * The attributes that should be visible, but masked in arrays, if used externally.
     *
     * @var array
     */
    protected $protected = [];

    /**
     * Rules for validating model data
     *
     * @type array
     */
    protected $rules = [];

    /**
     * Validation error messages
     *
     * @type array
     */
    protected $validationMessages = [];

    /**
     * Stores validation errors.
     *
     * @type array
     */
    protected $errors = [];

    /**
     * Validates data based on $this->rules.
     *
     * @param array     $data
     * @param bool|true $throwException
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public function validate(array $data = [], $throwException = true)
    {
        if (empty($data)) {
            $data = $this->attributes;
        }

        if (empty($this->rules) || empty($data)) {
            return true;
        } else {
            $validator = \Validator::make($data, $this->rules, $this->validationMessages);

            if ($validator->fails()) {
                $this->errors = $validator->errors()->getMessages();
                if ($throwException) {
                    $errorString = DataFormatter::validationErrorsToString($this->errors);
                    throw new BadRequestException('Invalid data supplied.' . $errorString, null, null, $this->errors);
                } else {
                    return false;
                }
            } else {
                return true;
            }
        }
    }

    public function errors()
    {
        return $this->errors;
    }

    public function save(array $options = [])
    {
        if ($this->validate()) {
            return parent::save($options);
        } else {
            return false;
        }
    }

    /**
     * Save a new model and return the instance.
     *
     * @param array $attributes
     *
     * @return BaseModel
     * @throws \Exception
     */
    public static function create(array $attributes = [])
    {
        $m = new static;
        $relations = [];
        $transaction = false;

        foreach ($attributes as $key => $value) {
            if ($m->isRelationMapped($key)) {
                $relations[$key] = $value;
                unset($attributes[$key]);
            }
        }

        if (count($relations) > 0) {
            DB::beginTransaction();
            $transaction = true;
        }

        try {
            /** @var BaseModel $model */
            $model = new static($attributes);

            $userId = SessionUtility::getCurrentUserId();
            if ($userId && static::isField('created_by_id')) {
                $model->created_by_id = $userId;
            }

            $model->save();

            foreach ($relations as $name => $value) {
                $relatedModel = $model->getReferencingModel($name);
                $newModels = [];

                if (!is_array($value)) {
                    throw new BadRequestException('Bad data supplied for ' .
                        $name .
                        '. Related data must be supplied as array.');
                }

                foreach ($value as $record) {
                    $newModels[] = new $relatedModel($record);
                }

                $relationType = $model->getReferencingType($name);
                if (RelationSchema::HAS_MANY === $relationType) {
                    $model->getHasManyByRelationName($name)->saveMany($newModels);
                } else {
                    throw new NotImplementedException('Creating related record of relation type "' .
                        $relationType .
                        '" is not supported.');
                }
            }

            if ($transaction) {
                DB::commit();
            }
        } catch (\Exception $e) {
            if ($transaction) {
                DB::rollBack();
            }

            throw $e;
        }

        return $model;
    }

    public static function isField($field)
    {
        $m = new static;
        /** @type TableSchema $tableSchema */
        $tableSchema = $m->getTableSchema();

        return (null !== $tableSchema->getColumn($field));
    }

    /**
     * @param       $records
     * @param array $params
     * @param bool  $singlePayload
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkCreate($records, $params = [], $singlePayload = false)
    {
        if (empty($records)) {
            throw new BadRequestException('There are no record sets in the request.');
        }

        $singleRow = (1 === count($records)) ? true : false;
        $response = [];
        $transaction = false;
        $errors = [];
        $rollback = Scalar::boolval(array_get($params, ApiOptions::ROLLBACK));
        $continue = Scalar::boolval(array_get($params, ApiOptions::CONTINUES));

        try {
            //	Start a transaction
            if (!$singleRow && $rollback) {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ($records as $key => $record) {
                try {
                    $response[$key] = static::createInternal($record, $params);
                } catch (\Exception $ex) {
                    if ($singleRow) {
                        throw $ex;
                    }

                    if ($rollback && $transaction) {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if (!$continue) {
                        break;
                    }
                }
            }
        } catch (\Exception $ex) {
            throw $ex;
        }

        if (!empty($errors)) {
            $msg = ['errors' => $errors, ResourcesWrapper::getWrapper() => $response];
            throw new BadRequestException("Batch Error: Not all parts of the request were successful.", null, null,
                $msg);
        }

        //	Commit
        if ($transaction) {
            try {
                DB::commit();
            } catch (\Exception $ex) {
                throw $ex;
            }
        }

        return $singlePayload ? current($response) : $response;
    }

    /**
     * @param       $record
     * @param array $params
     *
     * @return array
     */
    protected static function createInternal($record, $params = [])
    {
        try {
            $model = static::create($record);
        } catch (\PDOException $e) {
            throw $e;
        }

        return static::buildResult($model, $params);
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function createById($id, $record, $params = [])
    {
        $m = new static;
        $pk = $m->getPrimaryKey();
        $record[$pk] = $id;

        return static::bulkCreate([$record], $params, true);
    }

    /**
     * Update the model in the database.
     *
     * @param array $attributes
     *
     * @param array $options
     * @return bool|int
     * @throws \Exception
     */
    public function update(array $attributes = [], array $options = [])
    {
        $relations = [];
        $transaction = false;

        foreach ($attributes as $key => $value) {
            if ($this->isRelationMapped($key)) {
                $relations[$key] = $value;
                unset($attributes[$key]);
            }
        }

        if (count($relations) > 0) {
            DB::beginTransaction();
            $transaction = true;
        }

        try {
            $userId = SessionUtility::getCurrentUserId();
            if ($userId && static::isField('last_modified_by_id')) {
                $this->last_modified_by_id = $userId;
            }
            $updated = parent::update($attributes);

            if ($updated && $this->exists && count($relations) > 0) {
                foreach ($relations as $name => $value) {
                    $relatedModel = $this->getReferencingModel($name);

                    if (RelationSchema::HAS_MANY === $this->getReferencingType($name)) {
                        $hasMany = $this->getHasManyByRelationName($name);
                        $this->saveHasManyData($relatedModel, $hasMany, $value, $name);
                    }
                }
            }

            if ($transaction) {
                DB::commit();
            }
        } catch (\Exception $e) {
            if ($transaction) {
                DB::rollBack();
            }

            throw $e;
        }

        return $updated;
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function updateById($id, $record, $params = [])
    {
        $m = new static;
        $pk = $m->getPrimaryKey();
        $record[$pk] = $id;

        return static::bulkUpdate([$record], $params, true);
    }

    /**
     * @param       $ids
     * @param       $record
     * @param array $params
     *
     * @return array|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function updateByIds($ids, $record, $params = [])
    {
        if (!is_array($ids)) {
            $ids = explode(",", $ids);
        }

        $records = [];

        $m = new static;
        $pk = $m->getPrimaryKey();
        foreach ($ids as $id) {
            $record[$pk] = $id;
            $records[] = $record;
        }

        return static::bulkUpdate($records, $params);
    }

    /**
     * @param       $records
     * @param array $params
     * @param bool  $singlePayload
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkUpdate($records, $params = [], $singlePayload = false)
    {
        if (empty($records)) {
            throw new BadRequestException('There is no record in the request.');
        }

        $response = [];
        $transaction = null;
        $errors = [];
        $singleRow = (1 === count($records)) ? true : false;
        $rollback = Scalar::boolval(array_get($params, ApiOptions::ROLLBACK));
        $continue = Scalar::boolval(array_get($params, ApiOptions::CONTINUES));

        try {
            //	Start a transaction
            if (!$singleRow && $rollback) {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ($records as $key => $record) {
                try {
                    $m = new static;
                    $pk = $m->getPrimaryKey();
                    $id = array_get($record, $pk);
                    $response[$key] = static::updateInternal($id, $record, $params);
                } catch (\Exception $ex) {
                    if ($singleRow) {
                        throw $ex;
                    }

                    if ($rollback && $transaction) {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if (!$continue) {
                        break;
                    }
                }
            }
        } catch (\Exception $ex) {
            throw $ex;
        }

        if (!empty($errors)) {
            $msg = ['errors' => $errors, ResourcesWrapper::getWrapper() => $response];
            throw new BadRequestException("Batch Error: Not all parts of the request were successful.", null, null,
                $msg);
        }

        //	Commit
        if ($transaction) {
            try {
                DB::commit();
            } catch (\Exception $ex) {
                throw $ex;
            }
        }

        return $singlePayload ? current($response) : $response;
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    public static function updateInternal($id, $record, $params = [])
    {
        if (empty($record)) {
            throw new BadRequestException('There are no fields in the record to create . ');
        }

        if (empty($id)) {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException('Identifying field "id" can not be empty for update request . ');
        }

        $model = static::find($id);

        if (!$model instanceof Model) {
            throw new NotFoundException('No resource found for ' . $id);
        }

        $pk = $model->primaryKey;
        //	Remove the PK from the record since this is an update
        ArrayUtils::remove($record, $pk);

        try {
            $model->update($record);

            return static::buildResult($model, $params);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException('Failed to update resource: ' . $ex->getMessage());
        }
    }

    /**
     * @param       $id
     * @param array $params
     *
     * @return array|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function deleteById($id, $params = [])
    {
        $m = new static;
        $pk = $m->getPrimaryKey();

        return static::bulkDelete([[$pk => $id]], $params, true);
    }

    /**
     * @param       $ids
     * @param array $params
     *
     * @return array|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function deleteByIds($ids, $params = [])
    {
        if (!is_array($ids)) {
            $ids = explode(",", $ids);
        }

        $m = new static;
        $pk = $m->getPrimaryKey();
        $records = [];
        foreach ($ids as $id) {
            $records[] = [$pk => $id];
        }

        return static::bulkDelete($records, $params);
    }

    /**
     * @param       $records
     * @param array $params
     * @param bool  $singlePayload
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkDelete($records, $params = [], $singlePayload = false)
    {
        if (empty($records)) {
            throw new BadRequestException('There is no record in the request.');
        }

        $response = [];
        $transaction = null;
        $errors = [];
        $singleRow = (1 === count($records)) ? true : false;
        $rollback = Scalar::boolval(array_get($params, ApiOptions::ROLLBACK));
        $continue = Scalar::boolval(array_get($params, ApiOptions::CONTINUES));

        try {
            //	Start a transaction
            if (!$singleRow && $rollback) {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ($records as $key => $record) {
                try {
                    $m = new static;
                    $pk = $m->getPrimaryKey();
                    $id = array_get($record, $pk);
                    $response[$key] = static::deleteInternal($id, $record, $params);
                } catch (\Exception $ex) {
                    if ($singleRow) {
                        throw $ex;
                    }

                    if ($rollback && $transaction) {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if (!$continue) {
                        break;
                    }
                }
            }
        } catch (\Exception $ex) {
            throw $ex;
        }

        if (!empty($errors)) {
            $msg = ['errors' => $errors, ResourcesWrapper::getWrapper() => $response];
            throw new BadRequestException("Batch Error: Not all parts of the request were successful.", null, null,
                $msg);
        }

        //	Commit
        if ($transaction) {
            try {
                DB::commit();
            } catch (\Exception $ex) {
                throw $ex;
            }
        }

        return $singlePayload ? current($response) : $response;
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    public static function deleteInternal($id, $record, $params = [])
    {
        if (empty($record)) {
            throw new BadRequestException('There are no fields in the record to create . ');
        }

        if (empty($id)) {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException('Identifying field "id" can not be empty for update request . ');
        }

        $model = static::find($id);

        if (!$model instanceof Model) {
            throw new NotFoundException('No resource found for ' . $id);
        }

        try {
            $result = static::buildResult($model, $params);
            $model->delete();

            return $result;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException('Failed to delete resource: ' . $ex->getMessage());
        }
    }

    /**
     * @param BaseModel $model
     * @param array     $params
     *
     * @return array
     */
    public static function buildResult($model, $params = [])
    {
        $pk = $model->primaryKey;
        $id = $model->{$pk};
        $fields = array_get($params, ApiOptions::FIELDS, $pk);
        $related = array_get($params, ApiOptions::RELATED);

        if ($pk === $fields && empty($related)) {
            return [$pk => $id];
        }

        $fieldsArray = explode(',', $fields);
        $relatedArray = (!empty($related)) ? explode(',', $related) : [];

        $result = static::selectById($id, $relatedArray, $fieldsArray);

        return $result;
    }

    /**
     * Gets table references from table schema.
     *
     * @return array
     */
    public function getReferences()
    {
        return $this->getTableSchema()->relations;
    }

    /**
     * Gets the TableSchema for this model.
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        return $this->getSchema()->getTable($this->table);
    }

    public function getSchema()
    {
        $this->cachePrefix = 'model_' . $this->getTable() . ':';
        $this->getSchemaExtension($this->getConnection())->setCache($this);

        return $this->schemaExtension;
    }

    /**
     * Selects a model by id.
     *
     * @param integer $id
     * @param array   $related
     * @param array   $fields
     *
     * @return array
     */
    public static function selectById($id, array $related = [], array $fields = ['*'])
    {
        $model = static::with($related)->find($id, $fields);

        $data = (!empty($model)) ? $model->toArray() : [];

        return $data;
    }

    /**
     * Selects records by multiple ids.
     *
     * @param string|array $ids
     * @param array        $related
     * @param array        $criteria
     *
     * @return mixed
     */
    public static function selectByIds($ids, array $related = [], array $criteria = [])
    {
        if (empty($criteria)) {
            $criteria['select'] = ['*'];
        }

        if (is_array($ids)) {
            $ids = implode(',', $ids);
        }

        if (!empty($ids)) {
            $pk = static::getPrimaryKeyStatic();
            $idsPhrase = " $pk IN ($ids) ";

            $condition = array_get($criteria, 'condition');

            if (!empty($condition)) {
                $condition .= ' AND ' . $idsPhrase;
            } else {
                $condition = $idsPhrase;
            }

            $criteria['condition'] = $condition;
        }

        $data = static::selectByRequest($criteria, $related);

        return $data;
    }

    /**
     * Performs a SELECT query based on
     * query criteria supplied from api request.
     *
     * @param array $criteria
     * @param array $related
     *
     * @return array
     */
    public static function selectByRequest(array $criteria = [], array $related = [])
    {
        $pk = static::getPrimaryKeyStatic();
        $selection = array_get($criteria, 'select');
        if (empty($selection)) {
            $selection = ['*'];
        }

        $condition = array_get($criteria, 'condition');
        if (!empty($condition)) {
            $params = array_get($criteria, 'params');
            $builder = static::whereRaw($condition, $params)->with($related);
        } else {
            $builder = static::with($related);
        }

        if (!empty($limit = array_get($criteria, 'limit'))) {
            $builder->take($limit);
        }

        if (!empty($offset = array_get($criteria, 'offset'))) {
            $builder->skip($offset);
        }

        $orderBy = array_get($criteria, 'order', "$pk asc");
        $orders = explode(',', $orderBy);
        foreach ($orders as $order) {
            $order = trim($order);

            @list($column, $direction) = explode(' ', $order);
            if (empty($direction)) {
                $direction = 'ASC';
            }

            $builder = $builder->orderBy($column, $direction);
        }

        $groupBy = array_get($criteria, 'group');
        if (!empty($groupBy)) {
            $groups = explode(',', $groupBy);
            foreach ($groups as $group) {
                $builder = $builder->groupBy(trim($group));
            }
        }

        $collections = $builder->get($selection);
        $result = $collections->toArray();

        return $result;
    }

    /**
     * Performs a COUNT query based on query criteria supplied from api request.
     *
     * @param array $criteria
     *
     * @return int
     */
    public static function countByRequest(array $criteria = [])
    {
        if (!empty($condition = array_get($criteria, 'condition'))) {
            $params = array_get($criteria, 'params', []);

            return static::whereRaw($condition, $params)->count();
        }

        return static::count();
    }

    /**
     * Saves the HasMany relational data. If id exists
     * then it updates the record otherwise it will
     * create the record.
     *
     * @param BaseModel $relatedModel Model class
     * @param HasMany   $hasMany
     * @param           $data
     * @param           $relationName
     *
     * @throws \Exception
     */
    protected function saveHasManyData($relatedModel, HasMany $hasMany, $data, $relationName)
    {
        if ($this->exists) {
            $models = [];
            $pk = $hasMany->getRelated()->primaryKey;
            $fk = $hasMany->getPlainForeignKey();

            foreach ($data as $d) {
                /** @var Model $model */
                if (empty($pkValue = array_get($d, $pk))) {
                    $model = $relatedModel::findCompositeForeignKeyModel($this->{$this->primaryKey}, $d);
                } else {
                    $model = $relatedModel::find($pkValue);
                }
                if (!empty($model)) {
                    /** @var Model $parent */
                    $fkId = array_get($d, $fk);
                    if (array_key_exists($fk, $d) && is_null($fkId)) {
                        //Foreign key field is set to null therefore delete the child record.
                        $model->delete();
                        continue;
                    } elseif (!empty($fkId) &&
                        $fkId !== $this->{$this->primaryKey} &&
                        (null !== $parent = static::find($fkId))
                    ) {
                        //Foreign key field is set but the id belongs to a different parent than this parent.
                        //There the child is adopted by the supplied parent id (foreign key).
                        $relatedData = [$relationName => [$d]];
                        $parent->update($relatedData);
                        continue;
                    } else {
                        $model->fill($d);
                    }
                } else {
                    // not found, create a new one
                    $model = new $relatedModel($d);
                }
                $models[] = $model;
            }

            $hasMany->saveMany($models);
        }
    }

    /**
     * @param mixed $foreign Foreign key by which to search
     * @param array $data    Data containing possible other search attributes
     * @return Model
     */
    protected static function findCompositeForeignKeyModel(
        /** @noinspection PhpUnusedParameterInspection */
        $foreign,
        /** @noinspection PhpUnusedParameterInspection */
        $data
    ) {
        return null;
    }

    /**
     * Gets the HasMany model of the referencing table.
     *
     * @param string $table
     * @param string $relationName
     *
     * @return HasMany
     */
    protected function getHasMany($table, $relationName)
    {
        $model = SystemTableModelMapper::getModel($table);
        $refField = $this->getReferencingField($table, $relationName);

        return $this->hasMany($model, $refField);
    }

    /**
     * @param $name
     *
     * @return HasMany|null
     */
    public function getHasManyByRelationName($name)
    {
        $table = $this->getReferencingTable($name);

        if (!empty($table) && !is_null(SystemTableModelMapper::getModel($table))) {
            return $this->getHasMany($table, $name);
        } else {
            return null;
        }
    }

    public function getBelongsToManyByRelationName($name)
    {
        $table = $this->getReferencingTable($name);
        $model = SystemTableModelMapper::getModel($table);

        list($pivotTable, $fk, $rk) = $this->getReferencingJoin($name);

        return $this->belongsToMany($model, $pivotTable, $fk, $rk);
    }

    public function getBelongsToByRelationName($name)
    {
        $table = $this->getReferencingTable($name);
        $model = SystemTableModelMapper::getModel($table);

        $references = $this->getReferences();
        $lf = null;
        foreach ($references as $item) {
            if ($item->refTable === $table && $table . '_by_' . $item->field === $name) {
                $lf = $item->field;
            }
        }

        return $this->belongsTo($model, $lf);
    }

    /**
     * Gets the foreign key of the referenced table
     *
     * @param string $table
     * @param string $name
     *
     * @return mixed|null
     */
    protected function getReferencingField($table, $name)
    {
        $references = $this->getReferences();
        $rf = null;
        foreach ($references as $item) {
            if ($item->refTable === $table && $table . '_by_' . $item->refField === $name) {
                $rf = $item->refField;
                break;
            }
        }

        return $rf;
    }

    /**
     * Gets the referenced table name by it's relation name
     * to this model.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    protected function getReferencingTable($name)
    {
        $references = $this->getReferences();

        if (array_key_exists($name, $references)) {
            return $references[$name]->refTable;
        }

        return null;
    }

    public function getReferencingType($name)
    {
        $references = $this->getReferences();

        if (array_key_exists($name, $references)) {
            return $references[$name]->type;
        }

        return null;
    }

    protected function getReferencingJoin($name)
    {
        $references = $this->getReferences();

        if (array_key_exists($name, $references)) {
            if (!empty($pivotTable = $references[$name]->junctionTable)) {
                $fk = $references[$name]->junctionField;
                $rk = $references[$name]->junctionRefField;

                return [$pivotTable, $fk, $rk];
            }
        }

        return null;
    }

    /**
     * Gets the referenced model via its table using relation name.
     *
     * @param $name
     *
     * @return string
     */
    protected function getReferencingModel($name)
    {
        if (!$this->isRelationMapped($name)) {
            return null;
        }

        $table = $this->getReferencingTable($name);
        $model = SystemTableModelMapper::getModel($table);

        return $model;
    }

    /**
     * Checks to see if a relation is mapped in the
     * tableToModelMap array by the relation name
     *
     * @param $name
     *
     * @return bool
     */
    protected function isRelationMapped($name)
    {
        $table = $this->getReferencingTable($name);

        return !is_null(SystemTableModelMapper::getModel($table));
    }

    /**
     * Gets the primary key field.
     *
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * A static method to get the primary key
     *
     * @return string
     */
    public static function getPrimaryKeyStatic()
    {
        $m = new static;

        return $m->getPrimaryKey();
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newEloquentBuilder($query)
    {
        return new DfBuilder($query);
    }

    public static function getModelBaseName($fqcn)
    {
        if (preg_match('@\\\\([\w]+)$@', $fqcn, $matches)) {
            $fqcn = $matches[1];
        }

        return $fqcn;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeValue($key)
    {
        // if protected, no need to do anything else, mask it.
        if ($this->protectedView && in_array($key, $this->protected)) {
            return static::PROTECTION_MASK;
        }

        return parent::getAttributeValue($key);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeFromArray($key)
    {
        $value = parent::getAttributeFromArray($key);
        if (array_key_exists($key, $this->attributes) && in_array($key, $this->encrypted) && !empty($value)) {
            $value = Crypt::decrypt($value);
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // if protected, and trying to set the mask, throw it away
        if (in_array($key, $this->protected) && ($value === static::PROTECTION_MASK)) {
            return $this;
        }

        $return = parent::setAttribute($key, $value);

        if (array_key_exists($key, $this->attributes) && in_array($key, $this->encrypted)) {
            $this->attributes[$key] = Crypt::encrypt($this->attributes[$key]);
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        foreach ($attributes as $key => $value) {
            if ($this->protectedView && in_array($key, $this->protected)) {
                $attributes[$key] = static::PROTECTION_MASK;;
            } elseif (in_array($key, $this->encrypted) && !empty($attributes[$key])) {
                $attributes[$key] = Crypt::decrypt($value);
            }
        }

        return $attributes;
    }

    public function toApiDocsModel($name = null)
    {
        $schema = $this->getTableSchema();
        if ($schema) {
            $definition = static::fromTableSchema($schema);
            $requestFields = (isset($definition['properties']) ? $definition['properties'] : []);
            $returnable = array_flip($this->getArrayableItems(array_keys($schema->getColumnNames())));
            $responseFields = [];
            $required = (isset($definition['required']) ? $definition['required'] : []);
            foreach ($requestFields as $field => $value) {
                if (!$this->isFillable($field)) {
                    unset($requestFields[$field]);
                }

                if (array_key_exists($field, $returnable)) {
                    $responseFields[$field] = $value;
                }
            }

            $requestRelatives = [];
            $returnableRelatives = array_flip($this->getArrayableItems(array_keys($schema->relations)));
            $responseRelatives = [];
            // todo Need a workaround, the following is problematic due to some models not being directly exposed in API
            /** @var RelationSchema $relation */
            /*
            foreach ($schema->relations as $relation) {
                $refModel = static::tableNameToModel($relation->refTable);

                if (!empty($refModel)) {
                    $refModel = static::getModelBaseName($refModel);

                    switch ($relation->type) {
                        case RelationSchema::BELONGS_TO:
                            if ($this->isFillable($relation->name)) {
                                $requestRelatives[$relation->name] = [
                                    '$ref' => '#/definitions/Related' . $refModel . 'Request',
                                ];
                            }

                            if (array_key_exists($relation->name, $returnableRelatives)) {
                                $responseRelatives[$relation->name] = [
                                    '$ref' => '#/definitions/Related' . $refModel . 'Response',
                                ];
                            }
                            break;
                        case RelationSchema::HAS_MANY:
                            if ($this->isFillable($relation->name)) {
                                $requestRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => ['$ref' => '#/definitions/Related' . $refModel . 'Response'],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record directly",
                                ];
                            }

                            if (array_key_exists($relation->name, $returnableRelatives)) {
                                $responseRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => ['$ref' => '#/definitions/Related' . $refModel . 'Response'],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record directly",
                                ];
                            }
                            break;
                        case RelationSchema::MANY_MANY:
                            $pivotModel = static::tableNameToModel($relation->junctionTable);
                            $pivotModel = static::getModelBaseName($pivotModel);
                            $pivotModel = empty($pivotModel) ? $relation->junctionTable : $pivotModel;

                            if ($this->isFillable($relation->name)) {
                                $requestRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => ['$ref' => '#/definitions/Related' . $refModel . 'Request'],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record via the $pivotModel table.",
                                ];
                            }

                            if (array_key_exists($relation->name, $returnableRelatives)) {
                                $responseRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => ['$ref' => '#/definitions/Related' . $refModel . 'Response'],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record via the $pivotModel table.",
                                ];
                            }
                            break;
                    }
                }
            }
            */

            if (empty($name)) {
                $name = static::getModelBaseName(basename(get_class($this)));
            }

            $definitions = [];
            if (!empty($requestFields)) {
                $definitions[$name . 'Request'] = [
                    'type'       => 'object',
                    'required'   => $required,
                    'properties' => $requestFields + $requestRelatives
                ];
                $definitions['Related' . $name . 'Request'] = [
                    'type'       => 'object',
                    'required'   => $required,
                    'properties' => $requestFields
                ];
            }
            if (!empty($responseFields)) {
                $definitions[$name . 'Response'] = [
                    'type'       => 'object',
                    'properties' => $responseFields + $responseRelatives
                ];
                $definitions['Related' . $name . 'Response'] = [
                    'type'       => 'object',
                    'properties' => $responseFields
                ];
            }

            return $definitions;
        }

        return null;
    }
}