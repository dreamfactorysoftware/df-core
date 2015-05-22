<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Models;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Components\ConnectionAdapter;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\SqlDbCore\ColumnSchema;
use DreamFactory\Rave\SqlDbCore\RelationSchema;
use DreamFactory\Rave\SqlDbCore\Schema;
use DreamFactory\Rave\SqlDbCore\TableSchema;
use DreamFactory\Rave\Components\Builder as RaveBuilder;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Crypt;
use DB;

/**
 * Class BaseModel
 *
 * @package DreamFactory\Rave\Models
 */
class BaseModel extends Model
{
    const TABLE_TO_MODEL_MAP_CACHE_KEY = 'system.table_model_map';

    const TABLE_TO_MODEL_MAP_CACHE_TTL = 60;

    /**
     * SqlDbCore Schema object.
     *
     * @var Schema
     */
    protected $schema = null;

    /**
     * SqlDbCore TableSchema
     *
     * @var TableSchema
     */
    protected $tableSchema = null;

    /**
     * Array of table references from TableSchema
     *
     * @var array
     */
    protected $references = [ ];

    /**
     * Lists the config params (fields) that need to be encrypted
     *
     * @var array
     */
    protected $encrypted = [ ];

    /**
     * An array map of table names and their models
     * that are related to this model.
     *
     * array(
     *  'table_name' => 'ModelClass'
     * )
     *
     * @var array
     */
    protected static $tableToModelMap = [ ];

    /**
     * Save a new model and return the instance.
     *
     * @param array $attributes
     *
     * @return BaseModel
     * @throws \Exception
     */
    public static function create( array $attributes )
    {
        $m = new static;
        $relations = [ ];
        $transaction = false;

        foreach ( $attributes as $key => $value )
        {
            if ( $m->isRelationMapped( $key ) )
            {
                $relations[$key] = $value;
                unset( $attributes[$key] );
            }
        }

        if ( count( $relations ) > 0 )
        {
            DB::beginTransaction();
            $transaction = true;
        }

        try
        {
            /** @var BaseModel $model */
            $model = parent::create( $attributes );

            foreach ( $relations as $name => $value )
            {
                $relatedModel = $model->getReferencingModel( $name );
                $newModels = [ ];

                if ( !is_array( $value ) )
                {
                    throw new BadRequestException( 'Bad data supplied for ' . $name . '. Related data must be supplied as array.' );
                }

                foreach ( $value as $record )
                {
                    $newModels[] = new $relatedModel( $record );
                }

                if ( RelationSchema::HAS_MANY === $model->getReferencingType( $name ) )
                {
                    $model->getHasManyByRelationName( $name )->saveMany( $newModels );
                }
            }

            if ( $transaction )
            {
                DB::commit();
            }
        }
        catch ( \Exception $e )
        {
            if ( $transaction )
            {
                DB::rollBack();
            }

            throw $e;
        }

        return $model;
    }

    /**
     * @param       $records
     * @param array $params
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkCreate( $records, $params = [ ] )
    {
        if ( empty( $records ) )
        {
            throw new BadRequestException( 'There are no record sets in the request.' );
        }

        $singleRow = ( 1 === count( $records ) ) ? true : false;
        $response = array();
        $transaction = false;
        $errors = array();
        $rollback = ArrayUtils::getBool( $params, 'rollback' );
        $continue = ArrayUtils::getBool( $params, 'continue' );

        try
        {
            //	Start a transaction
            if ( !$singleRow && $rollback )
            {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ( $records as $key => $record )
            {
                try
                {
                    $response[$key] = static::createInternal( $record, $params );
                }
                catch ( \Exception $ex )
                {
                    if ( $singleRow )
                    {
                        throw $ex;
                    }

                    if ( $rollback && $transaction )
                    {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if ( !$continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }

        if ( !empty( $errors ) )
        {
            $msg = array( 'errors' => $errors, 'record' => $response );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $msg );
        }

        //	Commit
        if ( $transaction )
        {
            try
            {
                DB::commit();
            }
            catch ( \Exception $ex )
            {
                throw $ex;
            }
        }

        return $singleRow ? current( $response ) : array( 'record' => $response );
    }

    /**
     * @param       $record
     * @param array $params
     *
     * @return array
     */
    protected static function createInternal( $record, $params = [ ] )
    {
        try
        {
            $model = static::create( $record );
        }
        catch ( \PDOException $e )
        {
            throw $e;
        }

        return static::buildResult( $model, $params );
    }

    /**
     * Update the model in the database.
     *
     * @param array $attributes
     *
     * @return bool|int
     * @throws \Exception
     */
    public function update( array $attributes = [ ] )
    {
        $relations = [ ];
        $transaction = false;

        foreach ( $attributes as $key => $value )
        {
            if ( $this->isRelationMapped( $key ) )
            {
                $relations[$key] = $value;
                unset( $attributes[$key] );
            }
        }

        if ( count( $relations ) > 0 )
        {
            DB::beginTransaction();
            $transaction = true;
        }

        try
        {
            $updated = parent::update( $attributes );

            if ( $updated && $this->exists && count( $relations ) > 0 )
            {
                foreach ( $relations as $name => $value )
                {
                    $relatedModel = $this->getReferencingModel( $name );

                    if ( RelationSchema::HAS_MANY === $this->getReferencingType( $name ) )
                    {
                        $hasMany = $this->getHasManyByRelationName( $name );
                        $this->saveHasManyData( $relatedModel, $hasMany, $value, $name );
                    }
                }
            }

            if ( $transaction )
            {
                DB::commit();
            }
        }
        catch ( \Exception $e )
        {
            if ( $transaction )
            {
                DB::rollBack();
            }

            throw $e;
        }

        return $updated;
    }

    public static function updateById( $id, $record, $params = [ ] )
    {
        $m = new static;
        $pk = $m->getPrimaryKey();
        ArrayUtils::set( $record, $pk, $id );

        return static::bulkUpdate( array( $record ), $params );
    }

    public static function updateByIds( $ids, $record, $params = [ ] )
    {
        if ( !is_array( $ids ) )
        {
            $ids = explode( ",", $ids );
        }

        $records = [ ];

        $m = new static;
        $pk = $m->getPrimaryKey();
        foreach ( $ids as $id )
        {
            ArrayUtils::set( $record, $pk, $id );
            $records[] = $record;
        }

        return static::bulkUpdate( $records, $params );
    }

    /**
     * @param       $records
     * @param array $params
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkUpdate( $records, $params = [ ] )
    {
        if ( empty( $records ) )
        {
            throw new BadRequestException( 'There is no record in the request.' );
        }

        $response = array();
        $transaction = null;
        $errors = array();
        $singleRow = ( 1 === count( $records ) ) ? true : false;
        $rollback = ArrayUtils::getBool( $params, 'rollback' );
        $continue = ArrayUtils::getBool( $params, 'continue' );

        try
        {
            //	Start a transaction
            if ( !$singleRow && $rollback )
            {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ( $records as $key => $record )
            {
                try
                {
                    $m = new static;
                    $pk = $m->getPrimaryKey();
                    $id = ArrayUtils::get( $record, $pk );
                    $response[$key] = static::updateInternal( $id, $record, $params );
                }
                catch ( \Exception $ex )
                {
                    if ( $singleRow )
                    {
                        throw $ex;
                    }

                    if ( $rollback && $transaction )
                    {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if ( !$continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }

        if ( !empty( $errors ) )
        {
            $msg = array( 'errors' => $errors, 'record' => $response );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $msg );
        }

        //	Commit
        if ( $transaction )
        {
            try
            {
                DB::commit();
            }
            catch ( \Exception $ex )
            {
                throw $ex;
            }
        }

        return $singleRow ? current( $response ) : array( 'record' => $response );
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    public static function updateInternal( $id, $record, $params = [ ] )
    {
        if ( empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record to create . ' );
        }

        if ( empty( $id ) )
        {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException( 'Identifying field "id" can not be empty for update request . ' );
        }

        $model = static::find( $id );

        if ( !$model instanceof Model )
        {
            throw new ModelNotFoundException( 'No model found for ' . $id );
        }

        $pk = $model->primaryKey;
        //	Remove the PK from the record since this is an update
        ArrayUtils::remove( $record, $pk );

        try
        {
            $model->update( $record );

            return static::buildResult( $model, $params );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( 'Failed to update resource: ' . $ex->getMessage() );
        }
    }

    public static function deleteById( $id, $params = [ ] )
    {
        $records = array( array() );

        $m = new static;
        $pk = $m->getPrimaryKey();
        foreach ( $records as $key => $record )
        {
            ArrayUtils::set( $records[$key], $pk, $id );
        }

        return static::bulkDelete( $records, $params );
    }

    public static function deleteByIds( $ids, $params = [ ] )
    {
        if ( !is_array( $ids ) )
        {
            $ids = explode( ",", $ids );
        }

        $records = [ ];

        $m = new static;
        $pk = $m->getPrimaryKey();
        foreach ( $ids as $id )
        {
            $record = [ ];
            ArrayUtils::set( $record, $pk, $id );
            $records[] = $record;
        }

        return static::bulkDelete( $records, $params );
    }

    /**
     * @param       $records
     * @param array $params
     *
     * @return array|mixed
     * @throws BadRequestException
     * @throws \Exception
     */
    public static function bulkDelete( $records, $params = [ ] )
    {
        if ( empty( $records ) )
        {
            throw new BadRequestException( 'There is no record in the request.' );
        }

        $response = array();
        $transaction = null;
        $errors = array();
        $singleRow = ( 1 === count( $records ) ) ? true : false;
        $rollback = ArrayUtils::getBool( $params, 'rollback' );
        $continue = ArrayUtils::getBool( $params, 'continue' );

        try
        {
            //	Start a transaction
            if ( !$singleRow && $rollback )
            {
                DB::beginTransaction();
                $transaction = true;
            }

            foreach ( $records as $key => $record )
            {
                try
                {
                    $m = new static;
                    $pk = $m->getPrimaryKey();
                    $id = ArrayUtils::get( $record, $pk );
                    $response[$key] = static::deleteInternal( $id, $record, $params );
                }
                catch ( \Exception $ex )
                {
                    if ( $singleRow )
                    {
                        throw $ex;
                    }

                    if ( $rollback && $transaction )
                    {
                        DB::rollBack();
                        throw $ex;
                    }

                    // track the index of the error and copy error to results
                    $errors[] = $key;
                    $response[$key] = $ex->getMessage();
                    if ( !$continue )
                    {
                        break;
                    }
                }
            }
        }
        catch ( \Exception $ex )
        {
            throw $ex;
        }

        if ( !empty( $errors ) )
        {
            $msg = array( 'errors' => $errors, 'record' => $response );
            throw new BadRequestException( "Batch Error: Not all parts of the request were successful.", null, null, $msg );
        }

        //	Commit
        if ( $transaction )
        {
            try
            {
                DB::commit();
            }
            catch ( \Exception $ex )
            {
                throw $ex;
            }
        }

        return $singleRow ? current( $response ) : array( 'record' => $response );
    }

    /**
     * @param       $id
     * @param       $record
     * @param array $params
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    public static function deleteInternal( $id, $record, $params = [ ] )
    {
        if ( empty( $record ) )
        {
            throw new BadRequestException( 'There are no fields in the record to create . ' );
        }

        if ( empty( $id ) )
        {
            //Todo:perform logging below
            //Log::error( 'Update request with no id supplied: ' . print_r( $record, true ) );
            throw new BadRequestException( 'Identifying field "id" can not be empty for update request . ' );
        }

        $model = static::find( $id );

        if ( !$model instanceof Model )
        {
            throw new ModelNotFoundException( 'No model found for ' . $id );
        }

        try
        {
            $model->delete();
            $result = static::buildResult( $model, $params );

            return $result;
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( 'Failed to delete resource: ' . $ex->getMessage() );
        }
    }

    /**
     * @param       $model
     * @param array $params
     *
     * @return array
     */
    public static function buildResult( $model, $params = [ ] )
    {
        $pk = $model->primaryKey;
        $fields = ArrayUtils::get( $params, 'fields', $pk );

        $fieldsArray = explode( ",", $fields );

        $result = array();
        foreach ( $fieldsArray as $f )
        {
            $result[$f] = $model->{$f};
        }

        return $result;
    }

    /**
     * Gets the SqlDbCore Schema object.
     *
     * @return Schema
     * @throws \Exception
     */
    public function getSchema()
    {
        if ( empty( $this->schema ) )
        {
            $connection = $this->getConnection();
            $database = $connection->getDatabaseName();
            $host = $connection->getConfig( 'host' );
            $username = $connection->getConfig( 'username' );
            $password = $connection->getConfig( 'password' );
            $driver = $connection->getConfig( 'driver' );

            //Todo: This will only work for Mysql and Postgres. If we use other db for system this needs to account for that.
            $dsn = $driver . ":host=" . $host . ";dbname=" . $database;

            $adaptedConnection = new ConnectionAdapter( $dsn, $username, $password );
            $this->schema = $adaptedConnection->getSchema();
        }

        return $this->schema;
    }

    /**
     * Gets table references from table schema.
     *
     * @return array
     */
    public function getReferences()
    {
        if ( empty( $this->references ) )
        {
            if ( empty( $this->schema ) )
            {
                $this->getSchema();
            }
            if ( empty( $this->tableSchema ) )
            {
                $this->getTableSchema();
            }

            $this->references = $this->tableSchema->relations;
        }

        return $this->references;
    }

    /**
     * Gets the TableSchema for this model.
     *
     * @return TableSchema
     */
    public function getTableSchema()
    {
        if ( empty( $this->tableSchema ) )
        {
            if ( empty( $this->schema ) )
            {
                $this->getSchema();
            }
            $this->tableSchema = $this->schema->getTable( $this->table );
        }

        return $this->tableSchema;
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
    public static function selectById( $id, array $related = [ ], array $fields = [ '*' ] )
    {
        $model = static::with( $related )->find( $id, $fields );

        $data = ( !empty( $model ) ) ? $model->toArray() : [ ];

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
    public static function selectByIds( $ids, array $related = [ ], array $criteria = [ ] )
    {
        if ( empty( $criteria ) )
        {
            $criteria['select'] = [ '*' ];
        }

        if ( is_array( $ids ) )
        {
            $ids = implode( ',', $ids );
        }

        if ( !empty( $ids ) )
        {
            $pk = static::getPrimaryKeyStatic();
            $idsPhrase = " $pk IN ($ids) ";

            $condition = ArrayUtils::get( $criteria, 'condition' );

            if ( !empty( $condition ) )
            {
                $condition .= ' AND ' . $idsPhrase;
            }
            else
            {
                $condition = $idsPhrase;
            }

            ArrayUtils::set( $criteria, 'condition', $condition );
        }

        $data = static::selectByRequest( $criteria, $related );

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
    public static function selectByRequest( array $criteria = [ ], array $related = [ ] )
    {
        $pk = static::getPrimaryKeyStatic();
        $selection = ArrayUtils::get( $criteria, 'select' );
        $condition = ArrayUtils::get( $criteria, 'condition' );
        $limit = ArrayUtils::get( $criteria, 'limit', \Config::get( 'rave.db_max_records_returned' ) );
        $offset = ArrayUtils::get( $criteria, 'offset', 0 );
        $orderBy = ArrayUtils::get( $criteria, 'order', "$pk asc" );
        $orders = explode( ',', $orderBy );

        if ( !empty( $selection ) )
        {
            if ( !empty( $condition ) )
            {
                $builder = static::whereRaw( $condition )->with( $related )->skip( $offset )->take( $limit );
            }
            else
            {
                $builder = static::with( $related )->skip( $offset )->take( $limit );
            }

            foreach ( $orders as $order )
            {
                $order = trim( $order );
                list( $column, $direction ) = explode( ' ', $order );
                $builder = $builder->orderBy( $column, $direction );
            }

            $collections = $builder->get( $selection );
        }
        //This should never happen as $criteria['select'] is always '*' by default.
        else
        {
            $collections = static::skip( $offset )->take( $limit )->all();
        }

        $result = $collections->toArray();

        return $result;
    }

    /**
     * Saves the HasMany relational data. If id exists
     * then it updates the record otherwise it will
     * create the record.
     *
     * @param Model   $relatedModel Model class
     * @param HasMany $hasMany
     * @param         $data
     * @param         $relationName
     *
     * @throws \Exception
     */
    protected function saveHasManyData( $relatedModel, HasMany $hasMany, $data, $relationName )
    {
        if ( $this->exists )
        {
            $models = [ ];
            $pk = $hasMany->getRelated()->primaryKey;

            foreach ( $data as $d )
            {
                /** @var Model $model */
                $model = $relatedModel::find( ArrayUtils::get( $d, $pk ) );
                if ( !empty( $model ) )
                {
                    $fk = $hasMany->getPlainForeignKey();
                    $fkId = ArrayUtils::get( $d, $fk );
                    if ( null === $fkId )
                    {
                        //Foreign key field is null therefore delete the child record.
                        $model->delete();
                        continue;
                    }
                    elseif ( !empty( $fkId ) && $fkId !== $this->id && ( null !== $parent = static::find( $fkId ) ) )
                    {
                        //Foreign key field is set but the id belongs to a different parent than this parent.
                        //There the child is adopted by the supplied parent id (foreign key).
                        $relatedData = [ $relationName => [ $d ] ];
                        $parent->update( $relatedData );
                        continue;
                    }
                    else
                    {
                        $model->update( $d );
                        continue;
                    }
                }
                else
                {
                    $model = new $relatedModel( $d );
                }
                $models[] = $model;
            }

            $hasMany->saveMany( $models );
        }
    }

    /**
     * Gets the HasMany model of the referencing table.
     *
     * @param string $table
     * @param string $relationName
     *
     * @return HasMany
     */
    protected function getHasMany( $table, $relationName )
    {
        $model = $this->tableNameToModel( $table );
        $refField = $this->getReferencingField( $table, $relationName );

        return $this->hasMany( $model, $refField );
    }

    /**
     * @param $name
     *
     * @return HasMany|null
     */
    public function getHasManyByRelationName( $name )
    {
        $table = $this->getReferencingTable( $name );
        $mappedTables = array_keys( static::getTableToModelMap() );

        return ( !empty( $table ) && in_array( $table, $mappedTables ) ) ? $this->getHasMany( $table, $name ) : null;
    }

    public function getBelongsToManyByRelationName( $name )
    {
        $table = $this->getReferencingTable( $name );
        $model = $this->tableNameToModel( $table );

        list( $pivotTable, $fk, $rk ) = $this->getReferencingJoin( $name );

        return $this->belongsToMany( $model, $pivotTable, $fk, $rk );
    }

    public function getBelongsToByRelationName( $name )
    {
        $table = $this->getReferencingTable( $name );
        $model = $this->tableNameToModel( $table );

        $references = $this->getReferences();
        $lf = null;
        foreach ( $references as $item )
        {
            if ( $item->refTable === $table  && $table . '_by_' . $item->field === $name)
            {
                $lf = $item->field;
            }
        }

        return $this->belongsTo( $model, $lf );
    }

    /**
     * Gets the foreign key of the referenced table
     *
     * @param string $table
     * @param string $name
     *
     * @return mixed|null
     */
    protected function getReferencingField( $table, $name )
    {
        $references = $this->getReferences();
        $rf = null;
        foreach ( $references as $item )
        {
            if ( $item->refTable === $table && $table . '_by_' . $item->refFields === $name )
            {
                $rf = $item->refFields;
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
    protected function getReferencingTable( $name )
    {
        $references = $this->getReferences();

        if ( array_key_exists( $name, $references ) )
        {
            return $references[$name]->refTable;
        }

        return null;
    }

    public function getReferencingType( $name )
    {
        $references = $this->getReferences();

        if ( array_key_exists( $name, $references ) )
        {
            return $references[$name]->type;
        }

        return null;
    }

    protected function getReferencingJoin( $name )
    {
        $references = $this->getReferences();

        if ( array_key_exists( $name, $references ) )
        {
            $join = $references[$name]->join;
            if ( !empty( $join ) )
            {
                $pivotTable = substr( $join, 0, strpos( $join, '(' ) );
                $fields = substr( $join, ( strpos( $join, '(' ) + 1 ) );
                $fields = substr( $fields, 0, strlen( $fields ) - 1 );
                list( $fk, $rk ) = explode( ',', $fields );

                return [ $pivotTable, $fk, $rk ];
            }
        }

        return null;
    }

    /**
     * Gets the referenced model via its table using relation name.
     *
     * @param $name
     *
     * @return BaseModel
     */
    protected function getReferencingModel( $name )
    {
        if ( !$this->isRelationMapped( $name ) )
        {
            return null;
        }

        $table = $this->getReferencingTable( $name );
        $model = static::tableNameToModel( $table );

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
    protected function isRelationMapped( $name )
    {
        $table = $this->getReferencingTable( $name );

        return array_key_exists( $table, static::getTableToModelMap() );
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
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function newEloquentBuilder( $query )
    {
        return new RaveBuilder( $query );
    }

    public static function getTableToModelMap()
    {
        if ( empty( static::$tableToModelMap ) )
        {
            static::$tableToModelMap = \Cache::get( static::TABLE_TO_MODEL_MAP_CACHE_KEY, [ ] );
            if ( empty( static::$tableToModelMap ) )
            {
                static::$tableToModelMap = DB::table( 'db_table_extras' )->where( 'service_id', 1 )->lists( 'model', 'table' );
                \Cache::add( static::TABLE_TO_MODEL_MAP_CACHE_KEY, static::$tableToModelMap, static::TABLE_TO_MODEL_MAP_CACHE_TTL );
            }
        }

        return static::$tableToModelMap;
    }

    public static function tableNameToModel( $table )
    {
        $map = static::getTableToModelMap();

        return isset( $map[$table] ) ? $map[$table] : null;
    }

    public static function getModelBaseName( $fqcn )
    {
        if ( preg_match( '@\\\\([\w]+)$@', $fqcn, $matches ) )
        {
            $fqcn = $matches[1];
        }

        return $fqcn;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute( $key )
    {
        if ( in_array( $key, $this->encrypted ) && !empty( $this->attributes[$key] ) )
        {
            return Crypt::decrypt( $this->attributes[$key] );
        }

        return parent::getAttribute( $key );
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute( $key, $value )
    {
        if ( in_array( $key, $this->encrypted ) )
        {
            $value = Crypt::encrypt( $value );
        }

        parent::setAttribute( $key, $value );
    }

    /**
     * {@inheritdoc}
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        foreach ( $attributes as $key => $value )
        {
            if ( in_array( $key, $this->encrypted ) && !empty( $this->attributes[$key] ) )
            {
                $attributes[$key] = Crypt::decrypt( $value );
            }
        }

        return $attributes;
    }

    public function toApiDocsModel( $name = null )
    {
        $schema = $this->getTableSchema();
        if ( $schema )
        {
            $requestFields = [ ];
            $responseFields = array_flip( $this->getArrayableItems( array_keys( $schema->columns ) ) );
            /** @var ColumnSchema $field */
            foreach ( $schema->columns as $field )
            {
                if ( $this->isFillable( $field->name ) )
                {
                    $requestFields[$field->name] = [
                        'type'        => $field->type,
                        'description' => $field->comment,
                        'required'    => $field->determineRequired()
                    ];
                }

                if ( array_key_exists( $field->name, $responseFields ) )
                {
                    $responseFields[$field->name] = [
                        'type'        => $field->type,
                        'description' => $field->comment,
                        'required'    => $field->determineRequired()
                    ];
                }
            }

            $requestRelatives = [ ];
            $responseRelatives = array_flip( $this->getArrayableItems( array_keys( $schema->relations ) ) );
            /** @var RelationSchema $relation */
            foreach ( $schema->relations as $relation )
            {
                $refModel = static::tableNameToModel( $relation->refTable );

                if ( !empty( $refModel ) )
                {
                    $refModel = static::getModelBaseName( $refModel );

                    switch ( $relation->type )
                    {
                        case RelationSchema::BELONGS_TO:
                            if ( $this->isFillable( $relation->name ) )
                            {
                                $requestRelatives[$relation->name] = [
                                    'type'        => 'Related' . $refModel . 'Request',
                                    'description' => "A single $refModel record that this record potentially belongs to.",
                                    'required'    => false
                                ];
                            }

                            if ( array_key_exists( $relation->name, $responseRelatives ) )
                            {
                                $responseRelatives[$relation->name] = [
                                    'type'        => 'Related' . $refModel . 'Response',
                                    'description' => "A single $refModel record that this record potentially belongs to.",
                                    'required'    => false
                                ];
                            }
                            break;
                        case RelationSchema::HAS_MANY:
                            if ( $this->isFillable( $relation->name ) )
                            {
                                $requestRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => [ '$ref' => 'Related' . $refModel . 'Response' ],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record directly",
                                    'required'    => false
                                ];
                            }

                            if ( array_key_exists( $relation->name, $responseRelatives ) )
                            {
                                $responseRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => [ '$ref' => 'Related' . $refModel . 'Response' ],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record directly",
                                    'required'    => false
                                ];
                            }
                            break;
                        case RelationSchema::MANY_MANY:
                            $pivot = substr( $relation->join, 0, strpos( $relation->join, '(' ) );
                            $pivotModel = static::tableNameToModel( $pivot );
                            $pivotModel = static::getModelBaseName( $pivotModel );
                            $pivotModel = empty( $pivotModel ) ? $pivot : $pivotModel;

                            if ( $this->isFillable( $relation->name ) )
                            {
                                $requestRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => [ '$ref' => 'Related' . $refModel . 'Request' ],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record via the $pivotModel table.",
                                    'required'    => false
                                ];
                            }

                            if ( array_key_exists( $relation->name, $responseRelatives ) )
                            {
                                $responseRelatives[$relation->name] = [
                                    'type'        => 'array',
                                    'items'       => [ '$ref' => 'Related' . $refModel . 'Response' ],
                                    'description' => "Zero or more $refModel records that are potentially linked to this record via the $pivotModel table.",
                                    'required'    => false
                                ];
                            }
                            break;
                    }
                }
            }

            if ( empty( $name ) )
            {
                $name = static::getModelBaseName( basename( get_class( $this ) ) );
            }

            return [
                $name . 'Request'              => [
                    'id'         => $name . 'Request',
                    'properties' => $requestFields + $requestRelatives
                ],
                $name . 'Response'             => [
                    'id'         => $name . 'Response',
                    'properties' => $responseFields + $responseRelatives
                ],
                'Related' . $name . 'Request'  => [
                    'id'         => 'Related' . $name . 'Request',
                    'properties' => $requestFields
                ],
                'Related' . $name . 'Response' => [
                    'id'         => 'Related' . $name . 'Response',
                    'properties' => $responseFields
                ]
            ];
        }

        return null;
    }
}