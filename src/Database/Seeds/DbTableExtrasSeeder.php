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

use DreamFactory\Rave\Models\Service;

class DbTableExtrasSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Rave\\Models\\DbTableExtras';

    protected $recordIdentifier = 'table';

    protected $records = [
        [
            'table'      => 'user',
            'model'      => '\\DreamFactory\\Rave\\Models\\User',
        ],
        [
            'table'      => 'user_lookup',
            'model'      => '\\DreamFactory\\Rave\\Models\\UserLookup',
        ],
        [
            'table'      => 'service',
            'model'      => '\\DreamFactory\\Rave\\Models\\Service',
        ],
        [
            'table'      => 'service_type',
            'model'      => '\\DreamFactory\\Rave\\Models\\ServiceType',
        ],
        [
            'table'      => 'service_doc',
            'model'      => '\\DreamFactory\\Rave\\Models\\ServiceDoc',
        ],
        [
            'table'      => 'role',
            'model'      => '\\DreamFactory\\Rave\\Models\\Role',
        ],
        [
            'table'      => 'role_service_access',
            'model'      => '\\DreamFactory\\Rave\\Models\\RoleServiceAccess',
        ],
        [
            'table'      => 'role_lookup',
            'model'      => '\\DreamFactory\\Rave\\Models\\RoleLookup',
        ],
        [
            'table'      => 'app',
            'model'      => '\\DreamFactory\\Rave\\Models\\App',
        ],
        [
            'table'      => 'app_lookup',
            'model'      => '\\DreamFactory\\Rave\\Models\\AppLookup',
        ],
        [
            'table'      => 'app_group',
            'model'      => '\\DreamFactory\\Rave\\Models\\AppGroup',
        ],
        [
            'table'      => 'system_resource',
            'model'      => '\\DreamFactory\\Rave\\Models\\SystemResource',
        ],
        [
            'table'      => 'script_type',
            'model'      => '\\DreamFactory\\Rave\\Models\\ScriptType',
        ],
        [
            'table'      => 'event_script',
            'model'      => '\\DreamFactory\\Rave\\Models\\EventScript',
        ],
        [
            'table'      => 'event_subscriber',
            'model'      => '\\DreamFactory\\Rave\\Models\\EventSubscriber',
        ],
        [
            'table'      => 'email_template',
            'model'      => '\\DreamFactory\\Rave\\Models\\EmailTemplate',
        ],
        [
            'table'      => 'system_setting',
            'model'      => '\\DreamFactory\\Rave\\Models\\Setting',
        ],
        [
            'table'      => 'system_lookup',
            'model'      => '\\DreamFactory\\Rave\\Models\\Lookup',
        ]
    ];

    protected function getRecordExtras()
    {
        $systemServiceId = Service::whereType( 'system' )->pluck( 'id' );

        return         [
            'service_id' => $systemServiceId,
        ];
    }
}
