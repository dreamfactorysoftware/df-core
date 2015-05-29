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

use DreamFactory\Rave\Contracts\ServiceConfigHandlerInterface;
use DreamFactory\Rave\SqlDbCore\ColumnSchema;

/**
 * Class BaseServiceConfigModel
 *
 * @package DreamFactory\Rave\Models
 */
abstract class BaseServiceConfigModel extends BaseModel implements ServiceConfigHandlerInterface
{
    /**
     * @var string
     */
    protected $primaryKey = 'service_id';

    /**
     * @var array
     */
    protected $fillable = [ 'service_id' ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @param int $id
     *
     * @return array
     */
    public static function getConfig( $id )
    {
        $model = static::find( $id );

        if ( !empty( $model ) )
        {
            return $model->toArray();
        }
        else
        {
            return [ ];
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function validateConfig( $config )
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig( $id, $config )
    {
        $model = static::find( $id );
        if ( !empty( $model ) )
        {
            $model->update( $config );
        }
        else
        {
            //Making sure service_id is the first item in the config.
            //This way service_id will be set first and is available
            //for use right away. This helps setting an auto-generated
            //field that may depend on parent data. See OAuthConfig->setAttribute.
            $config = array_reverse( $config, true );
            $config['service_id'] = $id;
            $config = array_reverse( $config, true );
            static::create( $config );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function removeConfig( $id )
    {
        // deleting is not necessary here due to cascading on_delete relationship in database
    }

    /**
     * {@inheritdoc}
     */
    public static function getAvailableConfigs()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $model = new static;

        $schema = $model->getTableSchema();
        if ( $schema )
        {
            $out = [ ];
            foreach ( $schema->columns as $name => $column )
            {
                if ( 'service_id' === $name )
                {
                    continue;
                }

                /** @var ColumnSchema $column */
                $out[$name] = $column->toArray();
            }

            return $out;
        }

        return null;
    }
}