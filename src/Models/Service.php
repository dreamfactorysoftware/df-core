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

/**
 * Service
 *
 * @property integer $id
 * @property string  $name
 * @property string  $label
 * @property string  $description
 * @property boolean $is_active
 * @property string  $type
 * @property array   $config
 * @property integer $native_format_id
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Service whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|Service whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|Service whereLabel( $value )
 * @method static \Illuminate\Database\Query\Builder|Service whereIsActive( $value )
 * @method static \Illuminate\Database\Query\Builder|Service whereType( $value )
 * @method static \Illuminate\Database\Query\Builder|Service whereNativeFormatId( $value )
 * @method static \Illuminate\Database\Query\Builder|Service whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|Service whereLastModifiedDate( $value )
 */
class Service extends BaseSystemModel
{
    protected $table = 'service';

    protected $fillable = [ 'name', 'label', 'description', 'is_active', 'type', 'config' ];

    protected $guarded = [ 'id', 'created_date', 'last_modified_date' ];

    protected $appends = [ 'config' ];

    /**
     * @var array Extra config to pass to any config handler
     */
    protected $config = [ ];

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();

        static::created(
            function ( Service $service )
            {
                if ( !empty( $service->config ) )
                {
                    // take the type information and get the config_handler class
                    // set the config giving the service id and new config
                    $serviceCfg = $service->getConfigHandler();
                    if ( !empty( $serviceCfg ) )
                    {
                        return $serviceCfg::setConfig( $service->getKey(), $service->config );
                    }
                }

                return true;
            }
        );

        static::deleted(
            function ( Service $service )
            {
                // take the type information and get the config_handler class
                // set the config giving the service id and new config
                $serviceCfg = $service->getConfigHandler();
                if ( !empty( $serviceCfg ) )
                {
                    return $serviceCfg::removeConfig( $service->getKey() );
                }

                return true;
            }
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function serviceType()
    {
        return $this->belongsTo( 'DreamFactory\Rave\Models\ServiceType', 'type', 'name' );
    }

    /**
     * @param $name
     *
     * @return null
     */
    public static function getTypeByName( $name )
    {
        $_typeRec = static::whereName( $name )->get( [ 'type' ] )->first();

        return ( isset( $_typeRec, $_typeRec['type'] ) ) ? $_typeRec['type'] : null;
    }

    /**
     * @return array
     */
    public static function available()
    {
        return static::all( [ 'name', 'label' ] )->toArray();
    }

    /**
     * Determine the handler for the extra config settings
     *
     * @return ServiceConfigHandlerInterface|null
     */
    protected function getConfigHandler()
    {
        if ( null !== $typeInfo = $this->serviceType()->first() )
        {
            // lookup related service type config model
            return $typeInfo->config_handler;
        }

        return null;
    }

    /**
     * @return mixed
     */
    public function getConfigAttribute()
    {
        // take the type information and get the config_handler class
        // set the config giving the service id and new config
        $serviceCfg = $this->getConfigHandler();
        if ( !empty( $serviceCfg ) )
        {
            return $serviceCfg::getConfig( $this->getKey() );
        }

        return $this->config;
    }

    /**
     * @param array $val
     */
    public function setConfigAttribute( Array $val )
    {
        $this->config = $val;
        // take the type information and get the config_handler class
        // set the config giving the service id and new config
        $serviceCfg = $this->getConfigHandler();
        if ( !empty( $serviceCfg ) )
        {
            if ( $this->exists )
            {
                if ( $serviceCfg::validateConfig( $this->config ) )
                {
                    $serviceCfg::setConfig( $this->getKey(), $this->config );
                }
            }
            else
            {
                $serviceCfg::validateConfig( $this->config );
            }
        }
    }
}