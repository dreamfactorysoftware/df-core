<?php
namespace DreamFactory\Core\Database\Seeds;

use DreamFactory\Core\Models\Service;

class DbTableExtrasSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\DbTableExtras';

    protected $recordIdentifier = 'table';

    protected $records = [
        [
            'table' => 'user',
            'model' => '\\DreamFactory\\Core\\Models\\User',
        ],
        [
            'table' => 'user_lookup',
            'model' => '\\DreamFactory\\Core\\Models\\UserLookup',
        ],
        [
            'table' => 'user_to_app_to_role',
            'model' => '\\DreamFactory\\Core\\Models\\UserAppRole',
        ],
        [
            'table' => 'service',
            'model' => '\\DreamFactory\\Core\\Models\\Service',
        ],
        [
            'table' => 'service_type',
            'model' => '\\DreamFactory\\Core\\Models\\ServiceType',
        ],
        [
            'table' => 'service_doc',
            'model' => '\\DreamFactory\\Core\\Models\\ServiceDoc',
        ],
        [
            'table' => 'role',
            'model' => '\\DreamFactory\\Core\\Models\\Role',
        ],
        [
            'table' => 'role_service_access',
            'model' => '\\DreamFactory\\Core\\Models\\RoleServiceAccess',
        ],
        [
            'table' => 'role_lookup',
            'model' => '\\DreamFactory\\Core\\Models\\RoleLookup',
        ],
        [
            'table' => 'app',
            'model' => '\\DreamFactory\\Core\\Models\\App',
        ],
        [
            'table' => 'app_lookup',
            'model' => '\\DreamFactory\\Core\\Models\\AppLookup',
        ],
        [
            'table' => 'app_group',
            'model' => '\\DreamFactory\\Core\\Models\\AppGroup',
        ],
        [
            'table' => 'system_resource',
            'model' => '\\DreamFactory\\Core\\Models\\SystemResource',
        ],
        [
            'table' => 'script_type',
            'model' => '\\DreamFactory\\Core\\Models\\ScriptType',
        ],
        [
            'table' => 'event_script',
            'model' => '\\DreamFactory\\Core\\Models\\EventScript',
        ],
        [
            'table' => 'event_subscriber',
            'model' => '\\DreamFactory\\Core\\Models\\EventSubscriber',
        ],
        [
            'table' => 'email_template',
            'model' => '\\DreamFactory\\Core\\Models\\EmailTemplate',
        ],
        [
            'table' => 'system_setting',
            'model' => '\\DreamFactory\\Core\\Models\\Setting',
        ],
        [
            'table' => 'system_lookup',
            'model' => '\\DreamFactory\\Core\\Models\\Lookup',
        ]
    ];

    protected function getRecordExtras()
    {
        $systemServiceId = Service::whereType('system')->pluck('id');

        return [
            'service_id' => $systemServiceId,
        ];
    }
}
