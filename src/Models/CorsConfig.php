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


use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Rave\Exceptions\BadRequestException;

class CorsConfig extends BaseSystemModel
{
    protected $table = 'cors_config';

    protected $fillable = ['path', 'origin', 'header', 'method', 'max_age'];

    public $timestamps = false;

    public static function boot()
    {
        parent::boot();

        static::creating(
            function ( CorsConfig $config )
            {
                $config->validateAndClean();

                return true;
            }
        );

        static::updating(
            function ( CorsConfig $config)
            {
                $config->validateAndClean();

                return true;
            }
        );
    }

    public function validateAndClean()
    {
        $path = $this->getAttribute('path');
        $header = $this->getAttribute('header');
        $method = $this->getAttribute('method');

        if(empty($path))
        {
            throw new BadRequestException('No path specified. Use * to apply to all api paths.');
        }

        if(empty($header))
        {
            $this->setAttribute('header', '*');
        }

        if(is_string($method))
        {
            $method = explode(',', $method);
        }

        if(is_array($method))
        {
            $action = 0;
            foreach($method as $verb)
            {
                $action = $action | VerbsMask::toNumeric($verb);
            }
            $method = $action;
        }
        $this->setAttribute('method', $method);
    }

    public function getMethodAttribute($method)
    {
        if(is_array($method))
        {
            return $method;
        }
        return VerbsMask::maskToArray($method);
    }
}