<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
namespace DreamFactory\Rave\Database\Seeds;

class ServiceSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Rave\\Models\\Service';

    protected $records = [
        [
            'name'        => 'system',
            'label'       => 'System Management',
            'description' => 'Service for managing system resources.',
            'is_active'   => 1,
            'type'        => 'system',
            'mutable'     => 0,
            'deletable'   => 0
        ],
        [
            'name'        => 'api_docs',
            'label'       => 'Live API Docs',
            'description' => 'API documenting and testing service.',
            'is_active'   => 1,
            'type'        => 'swagger',
            'mutable'     => 0,
            'deletable'   => 0
        ],
        [
            'name'        => 'event',
            'label'       => 'Events',
            'description' => 'Service for displaying and subscribing to broadcast system events.',
            'is_active'   => 1,
            'type'        => 'event',
            'mutable'     => 0,
            'deletable'   => 0
        ]
    ];
}
