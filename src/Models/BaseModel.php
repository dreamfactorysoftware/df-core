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
    /** @var Schema  */
    protected $schema = null;

    /** @var TableSchema  */
    protected $tableSchema = null;

    /** @var array  */
    protected $references = [];

    /** @var array  */
    protected $disabledAppends = [];

    /** @var array  */
    protected static $relatedModels = [];

    /** @var array  */
    protected $dynamicFields = [];

    public function __construct(array $attributes = array())
    {
        if(!empty(static::$relatedModels))
        {
            $this->setupRelations();
        }
        parent::__construct($attributes);
        $this->disableRelated();
    }

    public static function boot()
    {
        parent::boot();

        if(!empty(static::$relatedModels))
        {
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

            $dsn = $driver . ":host=" . $host . ";dbname=" . $database;

            $adaptedConnection = new ConnectionAdapter( $dsn, $username, $password );
            $this->schema = $adaptedConnection->getSchema();
        }

        return $this->schema;
    }

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

    public function selectResponse( $criteria = null, $related = [])
    {
        $result = [];
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

        $result = ['record' => $collections->all()];

        return $result;
    }

    protected function saveHasManyData($relatedModel, HasMany $hasMany, $data)
    {
        if($this->exists)
        {
            $models = [ ];
            $pk = $hasMany->getRelated()->primaryKey;

            foreach ( $data as $d )
            {
                $model = $relatedModel::find( ArrayUtils::get( $d, $pk ) );
                if ( !empty( $model ) )
                {
                    $model->setRawAttributes( $d );
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

    public function disableRelated()
    {
        $this->disabledAppends = $this->appends;
        $this->appends = [];
    }

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

    protected function setupRelations()
    {
        $references = $this->getReferences();

        foreach($references as $ref)
        {
            $relationName = $ref['name'];
            if(ArrayUtils::get($ref, 'type')==='has_many' && $this->isRelationMapped($relationName))
            {
                $this->dynamicFields[$relationName] = [];
                $this->appends[] = $relationName;
                $this->fillable[] = $relationName;
            }
        }
    }

    protected function getHasMany($table)
    {
        $model = ArrayUtils::get(static::$relatedModels, $table);
        $refField = $this->getReferencingField($table);

        return $this->hasMany($model, $refField);
    }

    protected function getReferencingField($table)
    {
        $references = $this->getReferences();

        foreach($references as $ref)
        {
            if(ArrayUtils::get($ref, 'ref_table')===$table)
            {
                return ArrayUtils::get($ref, 'ref_field');
            }
        }
        return null;
    }

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

    protected function isRelationMapped($name)
    {
        $table = $this->getReferencingTable($name);
        $mappedTables = array_keys(static::$relatedModels);

        return in_array($table, $mappedTables);
    }

    /**
     * Set a given attribute on the model.
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
            $this->saveHasManyData($model, $this->getHasMany($table), $value);
        }
        else
        {
            parent::setAttribute( $key, $value );
        }
    }

    /**
     * @return string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }
}