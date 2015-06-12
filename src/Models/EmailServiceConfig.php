<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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

namespace DreamFactory\Core\Models;

class EmailServiceConfig extends BaseServiceConfigModel
{
    protected $table = 'email_config';

    protected $fillable = [ 'service_id', 'driver', 'host', 'port', 'encryption', 'username', 'password', 'command', 'parameters', 'key', 'secret', 'domain' ];

    protected $encrypted = [ 'username', 'password', 'key', 'secret' ];

    protected $appends = [ 'parameters' ];

    protected $parameters = [ ];

    public static function boot()
    {
        parent::boot();

        static::created(
            function ( EmailServiceConfig $emailConfig )
            {
                if ( !empty( $emailConfig->parameters ) )
                {
                    $params = [ ];
                    foreach ( $emailConfig->parameters as $param )
                    {
                        $params[] = new EmailServiceParameterConfig( $param );
                    }
                    $emailConfig->parameter()->saveMany( $params );
                }

                return true;
            }
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function parameter()
    {
        return $this->hasMany( 'DreamFactory\Core\Models\EmailServiceParameterConfig', 'service_id' );
    }

    /**
     * @return mixed
     */
    public function getParametersAttribute()
    {
        $this->parameters = $this->parameter()->get()->toArray();

        return $this->parameters;
    }

    /**
     * @param array $val
     */
    public function setParametersAttribute( Array $val )
    {
        $this->parameters = $val;
    }
}