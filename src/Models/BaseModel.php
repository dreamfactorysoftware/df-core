<?php

namespace DreamFactory\Core\Models;

use DB;
use DbSchemaExtensions;
use DreamFactory\Core\Components\Builder as DfBuilder;
use DreamFactory\Core\Components\Encryptable;
use DreamFactory\Core\Components\Protectable;
use DreamFactory\Core\Components\SchemaToOpenApiDefinition;
use DreamFactory\Core\Components\Validatable;
use DreamFactory\Core\Contracts\DbSchemaInterface;
use DreamFactory\Core\Database\Schema\RelationSchema;
use DreamFactory\Core\Database\Schema\TableSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\DbResourceTypes;
use DreamFactory\Core\Enums\DbSimpleTypes;
use DreamFactory\Core\Exceptions\BatchException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\Session as SessionUtility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use ServiceManager;
use SystemTableModelMapper;

/**
 * Class BaseModel
 *
 * @package DreamFactory\Core\Models
 */
class BaseModel extends Model
{
    use SchemaToOpenApiDefinition, Protectable, Encryptable, Validatable;

    /**
     * @var DbSchemaInterface
     */
    protected $schemaExtension;
    /**
     * @var string
     */
    protected $defaultSchema;

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
                $relatedModel = $model->getReferencingModel($name);
                $relationType = $model->getReferencingType($name);
                if (RelationSchema::HAS_ONE === $relationType) {
                    if (empty($value)) {
                        $model->getHasOneByRelationName($name)->delete();
                    } else {
                        $newModel = new $relatedModel($value);
                        $model->getHasOneByRelationName($name)->save($newModel);
                    }
                } elseif (RelationSchema::HAS_MANY === $relationType) {
                    if (empty($value)) {
                        continue;
                    }
                    $newModels = [];

                    if (!is_array($value)) {
                        throw new BadRequestException('Bad data supplied for ' .
                            $name .
                            '. Related data must be supplied as array.');
                    }

                    foreach ($value as $record) {
                        $newModels[] = new $relatedModel($record);
                    }

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

                    if (RelationSchema::HAS_ONE === $this->getReferencingType($name)) {
                        $hasOne = $this->getHasOneByRelationName($name);
                        $this->saveHasOneData($relatedModel, $hasOne, $value, $name);
                    } elseif (RelationSchema::HAS_MANY === $this->getReferencingType($name)) {
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

    protected function getTableConstraints($refresh = false)
    {
        $result = null;
        $cacheKey = 'system_table_constraints';
        if ($refresh || (is_null($result = \Cache::get($cacheKey)))) {
            $schema = $this->getSchema();
            if ($schema->supportsResourceType(DbResourceTypes::TYPE_TABLE_CONSTRAINT)) {
                $result = $schema->getResourceNames(DbResourceTypes::TYPE_TABLE_CONSTRAINT, $this->getDefaultSchema());
                \Cache::forever($cacheKey, $result);
            }
        }

        return $result;
    }

    protected function getDefaultSchema()
    {
        if (!$this->defaultSchema) {
            $this->defaultSchema = \Cache::rememberForever('default_schema', function () {
                return $this->getSchema()->getDefaultSchema();
            });
        }

        return $this->defaultSchema;
    }

    protected function updateTableWithConstraints(TableSchema $table, $constraints)
    {
        $serviceId = ServiceManager::getServiceIdByName('system');
        $defaultSchema = $this->getDefaultSchema();

        // handle local constraints
        $ts = strtolower($table->schemaName);
        $tn = strtolower($table->resourceName);
        if (isset($constraints[$ts][$tn])) {
            foreach ($constraints[$ts][$tn] as $conName => $constraint) {
                $table->constraints[strtolower($conName)] = $constraint;
                $cn = (array)$constraint['column_name'];
                $type = strtolower(array_get($constraint, 'constraint_type', ''));
                switch ($type[0]) {
                    case 'p':
                        foreach ($cn as $cndx => $colName) {
                            if ($column = $table->getColumn($colName)) {
                                $column->isPrimaryKey = true;
                                if ((1 === count($cn)) && $column->autoIncrement &&
                                    (DbSimpleTypes::TYPE_INTEGER === $column->type)) {
                                    $column->type = DbSimpleTypes::TYPE_ID;
                                }
                                $table->addColumn($column);
                                $table->addPrimaryKey($colName);
                            }
                        }
                        break;
                    case 'u':
                        foreach ($cn as $cndx => $colName) {
                            if ($column = $table->getColumn($colName)) {
                                $column->isUnique = true;
                                $table->addColumn($column);
                            }
                        }
                        break;
                    case 'f':
                        // belongs_to
                        $rts = array_get($constraint, 'referenced_table_schema', '');
                        $rtn = array_get($constraint, 'referenced_table_name', '');
                        $rcn = (array)array_get($constraint, 'referenced_column_name');
                        $name = ($rts == $defaultSchema) ? $rtn : $rts . '.' . $rtn;
                        foreach ($cn as $cndx => $colName) {
                            if ($column = $table->getColumn($colName)) {
                                $column->isForeignKey = true;
                                $column->refTable = $name;
                                $column->refField = array_get($rcn, $cndx);
                                if ((1 === count($rcn)) && (DbSimpleTypes::TYPE_INTEGER === $column->type)) {
                                    $column->type = DbSimpleTypes::TYPE_REF;
                                }
                                $table->addColumn($column);
                            }
                        }

                        // Add it to our foreign references as well
                        $relation = new RelationSchema([
                            'type'           => RelationSchema::BELONGS_TO,
                            'field'          => $cn,
                            'ref_service_id' => $serviceId,
                            'ref_table'      => $name,
                            'ref_field'      => $rcn,
                            'ref_on_update'  => array_get($constraint, 'update_rule'),
                            'ref_on_delete'  => array_get($constraint, 'delete_rule'),
                        ]);

                        $table->addRelation($relation);
                        break;
                }
            }
        }

        foreach ($constraints as $schemaName => $schemas) {
            foreach ($schemas as $tableName => $tables) {
                foreach ($tables as $constraintName => $constraint) {
                    if (0 !== strncasecmp('f', strtolower(array_get($constraint, 'constraint_type', '')), 1)) {
                        continue;
                    }

                    $rts = array_get($constraint, 'referenced_table_schema', '');
                    $rtn = array_get($constraint, 'referenced_table_name');
                    if ((0 === strcasecmp($rtn, $table->resourceName)) && (0 === strcasecmp($rts,
                                $table->schemaName))) {
                        $ts = array_get($constraint, 'table_schema', '');
                        $tn = array_get($constraint, 'table_name');
                        $tsk = strtolower($ts);
                        $tnk = strtolower($tn);
                        $cn = array_get($constraint, 'column_name');
                        $rcn = array_get($constraint, 'referenced_column_name');
                        $name = ($ts == $defaultSchema) ? $tn : $ts . '.' . $tn;
                        $type = RelationSchema::HAS_MANY;
                        if (isset($constraints[$tsk][$tnk])) {
                            foreach ($constraints[$tsk][$tnk] as $constraintName2 => $constraint2) {
                                $type2 = strtolower(array_get($constraint2, 'constraint_type', ''));
                                switch ($type2[0]) {
                                    case 'p':
                                    case 'u':
                                        // if this references a primary or unique constraint on the table then it is HAS_ONE
                                        $cn2 = $constraint2['column_name'];
                                        if ($cn2 === $cn) {
                                            $type = RelationSchema::HAS_ONE;
                                        }
                                        break;
                                    case 'f':
                                        // if other has foreign keys to other tables, we can say these are related as well
                                        $rts2 = array_get($constraint2, 'referenced_table_schema', '');
                                        $rtn2 = array_get($constraint2, 'referenced_table_name');
                                        if (!((0 === strcasecmp($rts2, $table->schemaName)) &&
                                            (0 === strcasecmp($rtn2, $table->resourceName)))
                                        ) {
                                            $name2 = ($rts2 == $defaultSchema) ? $rtn2 : $rts2 . '.' . $rtn2;
                                            $cn2 = array_get($constraint2, 'column_name');
                                            $rcn2 = array_get($constraint2, 'referenced_column_name');
                                            // not same as parent, i.e. via reference back to self
                                            // not the same key
                                            $relation =
                                                new RelationSchema([
                                                    'type'                => RelationSchema::MANY_MANY,
                                                    'field'               => $rcn,
                                                    'ref_service_id'      => $serviceId,
                                                    'ref_table'           => $name2,
                                                    'ref_field'           => $rcn2,
                                                    'ref_on_update'       => array_get($constraint, 'update_rule'),
                                                    'ref_on_delete'       => array_get($constraint, 'delete_rule'),
                                                    'junction_service_id' => $serviceId,
                                                    'junction_table'      => $name,
                                                    'junction_field'      => $cn,
                                                    'junction_ref_field'  => $cn2
                                                ]);

                                            $table->addRelation($relation);
                                        }
                                        break;
                                }
                            }

                            $relation = new RelationSchema([
                                'type'           => $type,
                                'field'          => $rcn,
                                'ref_service_id' => $serviceId,
                                'ref_table'      => $name,
                                'ref_field'      => $cn,
                                'ref_on_update'  => array_get($constraint, 'update_rule'),
                                'ref_on_delete'  => array_get($constraint, 'delete_rule'),
                            ]);

                            $table->addRelation($relation);
                        }
                    }
                }
            }
        }
    }

    /**
     * Gets the TableSchema for this model.
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        return \Cache::rememberForever('model:' . $this->table, function () {
            $resourceName = $this->table;
            $name = $resourceName;
            if (empty($schemaName = $this->getDefaultSchema())) {
                $internalName = $resourceName;
                $quotedName = $this->getSchema()->quoteTableName($resourceName);;
            } else {
                $internalName = $schemaName . '.' . $resourceName;
                $quotedName = $this->getSchema()->quoteTableName($schemaName) . '.' . $this->getSchema()->quoteTableName($resourceName);;
            }
            $settings = compact('schemaName', 'resourceName', 'name', 'internalName', 'quotedName');
            $tableSchema = new TableSchema($settings);
            $tableSchema = $this->getSchema()->getResource(DbResourceTypes::TYPE_TABLE, $tableSchema);
            // merge db relationships
            if (!empty($references = $this->getTableConstraints())) {
                $this->updateTableWithConstraints($tableSchema, $references);
            }

            $tableSchema->discoveryCompleted = true;

            return $tableSchema;
        });
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $conn = $this->getConnection();
            $driver = $conn->getDriverName();
            $this->schemaExtension = DbSchemaExtensions::getSchemaExtension($driver, $conn);
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
     * @param HasOne    $hasOne
     * @param           $data
     * @param           $relationName
     *
     * @throws \Exception
     */
    protected function saveHasOneData($relatedModel, HasOne $hasOne, $data, $relationName)
    {
        if ($this->exists) {
            $pk = $hasOne->getRelated()->primaryKey;
            $fk = $hasOne->getForeignKeyName();

            if (empty($data)) {
                // delete a related if it exists
                $hasOne->delete();
            } else {
                /** @var Model $model */
                if (empty($pkValue = array_get($data, $pk))) {
                    $model = $relatedModel::findCompositeForeignKeyModel($this->{$this->primaryKey}, $data);
                } else {
                    $model = $relatedModel::find($pkValue);
                }
                if (!empty($model)) {
                    /** @var Model $parent */
                    $fkId = array_get($data, $fk);
                    if (array_key_exists($fk, $data) && is_null($fkId)) {
                        //Foreign key field is set to null therefore delete the child record.
                        $model->delete();
                    } elseif (!empty($fkId) &&
                        $fkId !== $this->{$this->primaryKey} &&
                        (null !== $parent = static::find($fkId))
                    ) {
                        //Foreign key field is set but the id belongs to a different parent than this parent.
                        //There the child is adopted by the supplied parent id (foreign key).
                        $relatedData = [$relationName => $data];
                        $parent->update($relatedData);
                    } else {
                        $model->fill($data);
                        $hasOne->save($model);
                    }
                } else {
                    // not found, create a new one
                    $model = new $relatedModel($data);
                    $hasOne->save($model);
                }
            }
        }
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
     * @return HasOne|null
     */
    public function getHasOneByRelationName($name)
    {
        if ($table = $this->getReferencingTable($name)) {
            if ($model = static::getModelFromTable($table)) {
                $refField = $this->getReferencingField($table, $name);

                return $this->hasOne($model, $refField);
            }
        }

        return null;
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
            if ($item->refTable === $table && $table . '_by_' . implode('_', $item->field) === $name) {
                $lf = $item->field[0];
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
            if ($item->refTable === $table && $table . '_by_' . implode('_', $item->refField) === $name) {
                $rf = $item->refField[0];
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
                $fk = $references[$name]->junctionField[0];
                $rk = $references[$name]->junctionRefField[0];

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
                                    '$ref' => '#/components/schemas/Related' . $refModel . 'Request',
                                ];
                            }

                            if (array_key_exists($relation->name, $returnableRelatives)) {
                                $responseRelatives[$relation->name] = [
                                    '$ref' => '#/components/schemas/Related' . $refModel . 'Response',
                                ];
                            }
                            break;
                        case RelationSchema::HAS_MANY:
                            if ($this->isFillable($relation->name)) {
                                $requestRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => ['$ref' => '#/components/schemas/Related' . $refModel . 'Response'],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record directly",
                                ];
                            }

                            if (array_key_exists($relation->name, $returnableRelatives)) {
                                $responseRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => ['$ref' => '#/components/schemas/Related' . $refModel . 'Response'],
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
                                    'items'       => ['$ref' => '#/components/schemas/Related' . $refModel . 'Request'],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record via the $pivotModel table.",
                                ];
                            }

                            if (array_key_exists($relation->name, $returnableRelatives)) {
                                $responseRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => ['$ref' => '#/components/schemas/Related' . $refModel . 'Response'],
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
//                $definitions['Related' . $name . 'Request'] = [
//                    'type'       => 'object',
//                    'required'   => $required,
//                    'properties' => $requestFields
//                ];
            }
            if (!empty($responseFields)) {
                $definitions[$name . 'Response'] = [
                    'type'       => 'object',
                    'properties' => $responseFields + $responseRelatives
                ];
//                $definitions['Related' . $name . 'Response'] = [
//                    'type'       => 'object',
//                    'properties' => $responseFields
//                ];
            }

            return $definitions;
        }

        return null;
    }
}