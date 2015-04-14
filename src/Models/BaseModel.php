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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use DreamFactory\Rave\SqlDbCore\Schema;
use DreamFactory\Rave\SqlDbCore\TableSchema;

class BaseModel extends Model
{
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
    protected $references = [];

    /**
     * Appended fields that are disabled are
     * stored into this array. This is used
     * to only show related table data on demand.
     *
     * @var array
     */
    protected $disabledAppends = [];

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
    protected static $relatedModels = [];

    /**
     * Stores the field that holds related tables' data.
     * Typically the relation name.
     *
     * @var array
     */
    protected $dynamicFields = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(array $attributes = array())
    {
        if(!empty(static::$relatedModels))
        {
            $this->setupRelations();
        }
        parent::__construct($attributes);
        $this->disableRelated();
    }

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();

        if(!empty(static::$relatedModels))
        {
            // Storing the related table data after the parent record is created.
            // Todo: Implement ways to delete the newly created parent record when failed to save child record.
            static::created(
                function ( $myModel )
                {
                    foreach ( $myModel->dynamicFields as $key => $value )
                    {
                        if ( !empty( $value ) )
                        {
                            $table = $myModel->getReferencingTable( $key );
                            $model = ArrayUtils::get( static::$relatedModels, $table );
                            if ( !empty( $model ) )
                            {
                                $newModels = [ ];
                                foreach ( $value as $v )
                                {
                                    $newModels[] = new $model( $v );
                                }
                            }
                            $myModel->getHasMany( $table )->saveMany( $newModels );
                        }
                    }

                    return true;
                }
            );
        }
    }

    /**
     * Gets the SqlDbCore Schema object.
     *
     * @return Schema
     * @throws \Exception
     */
    public function getSchema()
    {
        if(empty($this->schema))
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
        if(empty($this->references))
        {
            if ( empty( $this->schema ) )
            {
                $this->getSchema();
            }
            if ( empty( $this->tableSchema ) )
            {
                $this->getTableSchema();
            }

            $this->references = $this->tableSchema->references;
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
        if(empty($this->tableSchema))
        {
            if ( empty( $this->schema ) )
            {
                $this->getSchema();
            }
            $this->tableSchema = $this->schema->getTable($this->table);
        }

        return $this->tableSchema;
    }

    /**
     * Performs a SELECT query based on
     * query criteria supplied from api request.
     *
     * @param null  $criteria
     * @param array $related
     *
     * @return array
     */
    public function selectResponse( $criteria = null, $related = [])
    {
        if ( empty( $criteria ) )
        {
            $collections = $this->all();

        }
        else
        {
            if ( !empty( $criteria['select'] ) )
            {
                $_fields = explode( ',', $criteria['select'] );

                $collections = $this->get($_fields);
            }
        }

        if(!empty($related))
        {
            $collections->each(
                function ( $item ) use ( $related )
                {
                    $item->enableRelated( $related );
                }
            );
        }

        $result = $collections->all();

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
    protected function saveHasManyData($relatedModel, HasMany $hasMany, $data, $relationName)
    {
        if($this->exists)
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
                    $fkId = ArrayUtils::get($d, $fk);
                    if(null === $fkId)
                    {
                        //Foreign key field is null therefore delete the child record.
                        $model->delete();
                        continue;
                    }
                    elseif(!empty($fkId) && $fkId !== $this->id && (null !== $parent = static::find($fkId)))
                    {
                        //Foreign key field is set but the id belongs to a different parent than this parent.
                        //There the child is adopted by the supplied parent id (foreign key).
                        $relatedData = [$relationName => [$d]];
                        $parent->update($relatedData);
                        continue;
                    }
                    else
                    {
                        $model->update($d);
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
     * Disables reads on all related tables by
     * removing the appended relation name from
     * $this->appended array to $this->disabledAppends.
     */
    public function disableRelated()
    {
        $this->disabledAppends = $this->appends;
        $this->appends = [];
    }

    /**
     * Enables reads on related tables by re-adding appended
     * relation name from $this->disabledAppends array to
     * $this->appends.
     *
     * @param array $relations
     */
    public function enableRelated(Array $relations = [])
    {
        if(empty($relations))
        {
            $this->appends = $this->disabledAppends;
            $this->disabledAppends = [ ];
        }
        else
        {
            foreach($relations as $relation)
            {
                if(in_array($relation, $this->disabledAppends))
                {
                    $this->appends[] = $relation;
                    $key = array_search($relation, $this->disabledAppends);
                    array_splice($this->disabledAppends, $key, 1);
                }
            }
        }
    }

    /**
     * Sets up models relations to other referencing tables
     * using the table-model map in static::$relatedModels and
     * table reference info in $this->references array.
     */
    protected function setupRelations()
    {
        $references = $this->getReferences();

        foreach($references as $ref)
        {
            $relationName = $ref['name'];
            if('has_many'===ArrayUtils::get($ref, 'type') && $this->isRelationMapped($relationName))
            {
                $this->dynamicFields[$relationName] = [];
                $this->appends[] = $relationName;
                $this->fillable[] = $relationName;
            }
        }
    }

    /**
     * Gets the HasMany model of the referencing table.
     *
     * @param string $table
     *
     * @return HasMany
     */
    protected function getHasMany($table)
    {
        $model = ArrayUtils::get(static::$relatedModels, $table);
        $refField = $this->getReferencingField($table);

        return $this->hasMany($model, $refField);
    }

    /**
     * Gets the foreign key of the referenced table
     *
     * @param string $table
     *
     * @return mixed|null
     */
    protected function getReferencingField($table)
    {
        $references = $this->getReferences();

        foreach($references as $ref)
        {
            if($table===ArrayUtils::get($ref, 'ref_table'))
            {
                return ArrayUtils::get($ref, 'ref_field');
            }
        }
        return null;
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

        foreach($references as $ref)
        {
            if(ArrayUtils::get($ref, 'name')===$name)
            {
                return ArrayUtils::get($ref, 'ref_table');
            }
        }
        return null;
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * Note: Overriding this method to perform the action
     * equivalent of having a getRelationNameAttribute method.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return mixed
     */
    protected function mutateAttribute($key, $value)
    {
        $method = 'get'.studly_case($key).'Attribute';

        if(method_exists($this, $method))
        {
            return $this->{'get' . studly_case( $key ) . 'Attribute'}( $value );
        }
        elseif(in_array($key, $this->appends) && empty($value))
        {
            $table = $this->getReferencingTable($key);
            $value = $this->getHasMany($table)->get()->toArray();
            $this->dynamicFields[$key] = $value;

            return $value;
        }
    }

    /**
     * Checks to see if a relation is mapped in the
     * static::$relatedModels array by the relation name
     *
     * @param $name
     *
     * @return bool
     */
    protected function isRelationMapped($name)
    {
        $table = $this->getReferencingTable($name);
        $mappedTables = array_keys(static::$relatedModels);

        return in_array($table, $mappedTables);
    }

    /**
     * Set a given attribute on the model.
     *
     * Note: Overriding this method to perform the action
     * equivalent of having a setRelationNameAttribute method.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        if ( !$this->hasSetMutator( $key ) && $this->isRelationMapped($key) )
        {
            $table = $this->getReferencingTable($key);
            $model = ArrayUtils::get(static::$relatedModels, $table);
            $this->dynamicFields[$key] = $value;
            $this->saveHasManyData($model, $this->getHasMany($table), $value, $key);
        }
        else
        {
            parent::setAttribute( $key, $value );
        }
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
}