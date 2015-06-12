<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Core\Database\Seeds;

class RoleAndAppSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\Role';

    protected $records = [
        [
            'name'                           => 'default_admin',
            'description'                    => 'Default admin app role allowing access to login.',
            'is_active'                      => 1,
            'role_service_access_by_role_id' => [
                [
                    'service_id'     => 1,
                    'component'      => 'config',
                    'verb_mask'      => 1,
                    'requestor_mask' => 3
                ]
            ],
            'app_by_role_id'                 => [
                [
                    'name'        => 'admin',
                    'api_key'     => '6498a8ad1beb9d84d63035c5d1120c007fad6de706734db9689f8996707e0f7d',
                    'description' => 'Default Admin Application',
                    'is_active'   => 1,
                    'type'        => 3,
                    'path'        => 'dsp-admin-app/app/index.html'
                ]
            ]
        ],
        [
            'name'                           => 'default_swagger',
            'description'                    => 'Default swagger app role allowing access to api_docs.',
            'is_active'                      => 1,
            'role_service_access_by_role_id' => [
                [
                    'service_id'     => 2,
                    'component'      => '*',
                    'verb_mask'      => 1,
                    'requestor_mask' => 1
                ]
            ],
            'app_by_role_id'                 => [
                [
                    'name'        => 'swagger',
                    'api_key'     => 'daa2cc418f2b966effe794737c9e32bd651701142d6c86cba18f4070efecbf3c',
                    'description' => 'Swagger API documentation user interface application.',
                    'is_active'   => 1,
                    'type'        => 3,
                    'path'        => 'swagger/index.html'
                ]
            ]
        ]
    ];
}
