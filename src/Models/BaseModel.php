<?php

namespace DreamFactory\Core\Models;

use DB;
use DbSchemaExtensions;
use DreamFactory\Core\Components\Builder as DfBuilder;
use DreamFactory\Core\Components\Cacheable;
use DreamFactory\Core\Components\Encryptable;
use DreamFactory\Core\Components\Protectable;
use DreamFactory\Core\Components\SchemaToOpenApiDefinition;
use DreamFactory\Core\Components\Validatable;
use DreamFactory\Core\Contracts\CacheInterface;
use DreamFactory\Core\Contracts\SchemaInterface;
use DreamFactory\Core\Database\Schema\RelationSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\DbResourceTypes;
use DreamFactory\Core\Exceptions\BatchException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\Session as SessionUtility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use SystemTableModelMapper;

/**
 * Class BaseModel
 *
 * @package DreamFactory\Core\Models
 */
class BaseModel extends Model implements CacheInterface
{
    use Cacheable, SchemaToOpenApiDefinition, Protectable, Encryptable, Validatable;

    /**
     * @var SchemaInterface
     */
    protected $schemaExtension;

    public function save(array $options = [])
    {
        if ($this->validate($this->attributes)) {
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
                if (empty($value)) {
                    continue;
                }
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
     * Removes 'config' from select criteria if supplied as it chokes the model.
     *
     * @param array $criteria
     *
     * @return array
     */
    protected static function cleanCriteria(array $criteria)
    {
        $fields = array_get($criteria, 'select');
        $criteria['select'] = static::cleanFields($fields);

        return $criteria;
    }

    /**
     * Removes unwanted fields from field list if supplied.
     *
     * @param mixed $fields
     *
     * @return array
     */
    public static function cleanFields($fields)
    {
        if (!is_array($fields)) {
            $fields = explode(',', $fields);
        }

        return $fields;
    }

    /**
     * If fields is not '*' (all) then clean out any unwanted properties.
     *
     * @param mixed $response
     * @param mixed $fields
     *
     * @return array
     */
    protected static function cleanResult(
        $response,
        /** @noinspection PhpUnusedParameterInspection */
        $fields
    ) {
        // for collections and models
        if (is_object($response) && method_exists($response, 'toArray')) {
            return $response->toArray();
        }

        return $response;
    }

    /**
     * @param       $records
     * @param array $params
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkCreate(array $records, array $params = [])
    {
        if (empty($records)) {
            throw new BadRequestException('There are no record sets in the request.');
        }

        $response = [];
        $errors = false;
        $rollback = array_get_bool($params, ApiOptions::ROLLBACK);
        $continue = array_get_bool($params, ApiOptions::CONTINUES);

        if ($rollback) {
            //	Start a transaction
            DB::beginTransaction();
        }

        foreach ($records as $key => $record) {
            try {
                $response[$key] = static::createInternal($record, $params);
            } catch (\Exception $ex) {
                $errors = true;
                $response[$key] = $ex;
                // track the index of the error and copy error to results
                if ($rollback || !$continue) {
                    break;
                }
            }
        }

        if ($errors) {
            $msg = "Batch Error: Not all requested records could be created.";
            if ($rollback) {
                DB::rollBack();
                $msg .= " All changes rolled back.";
            }

            throw new BatchException($response, $msg);
        }

        if ($rollback) {
            DB::commit();
        }

        return $response;
    }

    /**
     * @param       $record
     * @param array $params
     *
     * @return array
     */
    protected static function createInternal($record, $params = [])
    {
        $model = static::create($record);

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

        try {
            $response = static::bulkCreate([$record], $params);

            return current($response);
        } catch (BatchException $ex) {
            $response = $ex->pickResponse(0);
            if ($response instanceof \Exception) {
                throw $response;
            }

            return $response;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException($ex->getMessage());
        }
    }

    /**
     * Update the model in the database.
     *
     * @param array $attributes
     *
     * @param array $options
     *
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
    public static function updateById($id, array $record, array $params = [])
    {
        $m = new static;
        $pk = $m->getPrimaryKey();
        $record[$pk] = $id;

        try {
            $response = static::bulkUpdate([$record], $params);

            return current($response);
        } catch (BatchException $ex) {
            $response = $ex->pickResponse(0);
            if ($response instanceof \Exception) {
                throw $response;
            }

            return $response;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException($ex->getMessage());
        }
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
    public static function updateByIds($ids, array $record, array $params = [])
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
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkUpdate(array $records, array $params = [])
    {
        if (empty($records)) {
            throw new BadRequestException('There is no record in the request.');
        }

        $response = [];
        $errors = false;
        $rollback = array_get_bool($params, ApiOptions::ROLLBACK);
        $continue = array_get_bool($params, ApiOptions::CONTINUES);

        if ($rollback) {
            //	Start a transaction
            DB::beginTransaction();
        }

        foreach ($records as $key => $record) {
            try {
                $m = new static;
                $pk = $m->getPrimaryKey();
                $id = array_get($record, $pk);
                $response[$key] = static::updateInternal($id, $record, $params);
            } catch (\Exception $ex) {
                // track the index of the error and copy error to results
                $errors = true;
                $response[$key] = $ex;
                if ($rollback || !$continue) {
                    break;
                }
            }
        }

        if ($errors) {
            $msg = "Batch Error: Not all requested records could be updated.";
            if ($rollback) {
                DB::rollBack();
                $msg .= " All changes rolled back.";
            }

            throw new BatchException($response, $msg);
        }

        if ($rollback) {
            DB::commit();
        }

        return $response;
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
            throw new NotFoundException("Record with identifier '$id' not found.");
        }

        $pk = $model->primaryKey;
        //	Remove the PK from the record since this is an update
        unset($record[$pk]);

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
    public static function deleteById($id, array $params = [])
    {
        $m = new static;
        $pk = $m->getPrimaryKey();

        try {
            $response = static::bulkDelete([[$pk => $id]], $params);

            return current($response);
        } catch (BatchException $ex) {
            $response = $ex->pickResponse(0);
            if ($response instanceof \Exception) {
                throw $response;
            }

            return $response;
        } catch (\Exception $ex) {
            throw new InternalServerErrorException($ex->getMessage());
        }
    }

    /**
     * @param       $ids
     * @param array $params
     *
     * @return array|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function deleteByIds($ids, array $params = [])
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
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkDelete(array $records, array $params = [])
    {
        if (empty($records)) {
            throw new BadRequestException('There is no record in the request.');
        }

        $response = [];
        $errors = false;
        $rollback = array_get_bool($params, ApiOptions::ROLLBACK);
        $continue = array_get_bool($params, ApiOptions::CONTINUES);

        if ($rollback) {
            //	Start a transaction
            DB::beginTransaction();
        }

        foreach ($records as $key => $record) {
            try {
                $m = new static;
                $pk = $m->getPrimaryKey();
                $id = array_get($record, $pk);
                $response[$key] = static::deleteInternal($id, $record, $params);
            } catch (\Exception $ex) {
                // track the index of the error and copy error to results
                $errors = true;
                $response[$key] = $ex;
                if ($rollback || !$continue) {
                    break;
                }
            }
        }

        if ($errors) {
            $msg = "Batch Error: Not all requested records could be deleted.";
            if ($rollback) {
                DB::rollBack();
                $msg .= " All changes rolled back.";
            }

            throw new BatchException($response, $msg);
        }

        if ($rollback) {
            DB::commit();
        }

        return $response;
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
            throw new NotFoundException("Record with identifier '$id' not found.");
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

        $result = static::selectById($id, $params, $fieldsArray);

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
        return $this->getSchema()->getResource(DbResourceTypes::TYPE_TABLE, $this->table);
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $conn = $this->getConnection();
            $driver = $conn->getDriverName();
            if ($this->schemaExtension = DbSchemaExtensions::getSchemaExtension($driver, $conn)) {
                $this->cachePrefix = 'model_' . $this->getTable() . ':';
            }
        }

        return $this->schemaExtension;
    }

    /**
     * Selects a model by id.
     *
     * @param integer $id
     * @param array   $options
     * @param array   $fields
     *
     * @return array
     */
    public static function selectById($id, array $options = [], array $fields = ['*'])
    {
        $fields = static::cleanFields($fields);
        $related = array_get($options, ApiOptions::RELATED, []);
        if (is_string($related)) {
            $related = explode(',', $related);
        }

        if ($model = static::with($related)->find($id, $fields)) {
            return static::cleanResult($model, $fields);
        }

        return null;
    }

    /**
     * Selects records by multiple ids.
     *
     * @param string|array $ids
     * @param array        $options
     * @param array        $criteria
     *
     * @return mixed
     * @throws BatchException
     */
    public static function selectByIds($ids, array $options = [], array $criteria = [])
    {
        $criteria = static::cleanCriteria($criteria);
        if (empty($criteria)) {
            $criteria['select'] = ['*'];
        }

        if (is_array($ids)) {
            $ids = implode(',', $ids);
        }

        $pk = static::getPrimaryKeyStatic();
        if (!empty($ids)) {
            $idsPhrase = " $pk IN ($ids) ";

            $condition = array_get($criteria, 'condition');

            if (!empty($condition)) {
                $condition .= ' AND ' . $idsPhrase;
            } else {
                $condition = $idsPhrase;
            }

            $criteria['condition'] = $condition;
        }

        $data = static::selectByRequest($criteria, $options);

        $data = static::cleanResult($data, array_get($criteria, 'select'));
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        if (count($data) != count($ids)) {
            $out = [];
            $continue = array_get_bool($options, ApiOptions::CONTINUES);
            foreach ($ids as $index => $id) {
                $found = false;
                foreach ($data as $record) {
                    if ($id == array_get($record, $pk)) {
                        $out[$index] = $record;
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $out[$index] = new NotFoundException("Record with identifier '$id' not found.");
                    if (!$continue) {
                        break;
                    }
                }
            }

            throw new BatchException($out, 'Batch Error: Not all requested records could be retrieved.');
        }

        return $data;
    }

    /**
     * Performs a SELECT query based on
     * query criteria supplied from api request.
     *
     * @param array $criteria
     * @param array $options
     *
     * @return array
     */
    public static function selectByRequest(array $criteria = [], array $options = [])
    {
        $criteria = static::cleanCriteria($criteria);
        $pk = static::getPrimaryKeyStatic();
        $selection = array_get($criteria, 'select');
        if (empty($selection)) {
            $selection = ['*'];
        }

        $related = array_get($options, ApiOptions::RELATED, []);
        if (is_string($related)) {
            $related = explode(',', $related);
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

        $response = $builder->get($selection);

        return static::cleanResult($response, array_get($criteria, 'select'));
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
            $fk = $hasMany->getForeignKeyName();

            foreach ((array)$data as $d) {
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
     *
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
     * @param $table
     *
     * @return string|null
     */
    protected static function getModelFromTable($table)
    {
        return SystemTableModelMapper::getModel($table);
    }

    /**
     * @param $name
     *
     * @return HasMany|null
     */
    public function getHasManyByRelationName($name)
    {
        if ($table = $this->getReferencingTable($name)) {
            if ($model = static::getModelFromTable($table)) {
                $refField = $this->getReferencingField($table, $name);

                return $this->hasMany($model, $refField);
            }
        }

        return null;
    }

    public function getBelongsToManyByRelationName($name)
    {
        $table = $this->getReferencingTable($name);
        $model = static::getModelFromTable($table);

        list($pivotTable, $fk, $rk) = $this->getReferencingJoin($name);

        return $this->belongsToMany($model, $pivotTable, $fk, $rk);
    }

    public function getBelongsToByRelationName($name)
    {
        $table = $this->getReferencingTable($name);
        $model = static::getModelFromTable($table);

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
        $model = static::getModelFromTable($table);

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

        return !is_null(static::getModelFromTable($table));
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
        $value = parent::getAttributeValue($key);
        $this->protectAttribute($key, $value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeFromArray($key)
    {
        $value = parent::getAttributeFromArray($key);
        $this->decryptAttribute($key, $value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // if protected, and trying to set the mask, throw it away
        if ($this->isProtectedAttribute($key, $value)) {
            return $this;
        }

        $return = parent::setAttribute($key, $value);

        if (array_key_exists($key, $this->attributes)) {
            $value = $this->attributes[$key];
            $this->encryptAttribute($key, $value);
            $this->attributes[$key] = $value;
        }

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        $attributes = $this->addDecryptedAttributesToArray($attributes);

        $attributes = $this->addProtectedAttributesToArray($attributes);

        return $attributes;
    }

    public function toApiDocsModel($name = null)
    {
        $schema = $this->getTableSchema();
        if ($schema) {
            $definition = static::fromTableSchema($schema);
            $requestFields = (isset($definition['properties']) ? $definition['properties'] : []);
            $returnable = array_flip($this->getArrayableItems($schema->getColumnNames()));
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