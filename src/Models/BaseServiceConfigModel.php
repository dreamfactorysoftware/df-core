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

use Crypt;
use DreamFactory\Rave\Contracts\ServiceConfigHandlerInterface;

/**
 * Class BaseServiceConfigModel
 *
 * @package DreamFactory\Rave\Models
 */
abstract class BaseServiceConfigModel extends BaseModel implements ServiceConfigHandlerInterface
{
    /**
     * Lists the config params (fields) that need to be encrypted
     *
     * @var array
     */
    protected $encrypted = [ ];

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
            $config['service_id'] = $id;
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
}