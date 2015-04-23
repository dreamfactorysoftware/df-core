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

class App extends BaseSystemModel
{
    protected $table = 'app';

    protected $fillable = [
        'name',
        'api_key',
        'description',
        'is_active',
        'type',
        'url',
        'storage_service_id',
        'storage_container',
        'import_url',
        'requires_fullscreen',
        'allow_fullscreen_toggle',
        'toggle_location',
        'role_id'
    ];

    protected static $relatedModels = [
        'role'             => 'DreamFactory\Rave\Models\Role',
        'user_to_app_role' => 'DreamFactory\Rave\Models\UserAppRole'
    ];

    public static function generateApiKey( $name )
    {
        $string = gethostname() . $name . time();
        $key = hash( 'sha256', $string );

        return $key;
    }

    public static function seed()
    {
        $seeded = false;

        if ( !static::whereId( 1 )->exists() )
        {
            $name = 'default';
            $apiKey = static::generateApiKey( $name );
            static::create(
                [
                    'id'                      => 1,
                    'name'                    => $name,
                    'api_key'                 => $apiKey,
                    'description'             => 'This "App" is primarily used for allowing access to the system using api key.',
                    'is_active'               => 1,
                    'type'                    => 1,
                    'allow_fullscreen_toggle' => 0,
                    'role_id'                 => 1
                ]
            );
            $seeded = true;
        }

        return $seeded;
    }
}