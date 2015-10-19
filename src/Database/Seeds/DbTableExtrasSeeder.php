<?php
namespace DreamFactory\Core\Database\Seeds;

use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\AppGroup;
use DreamFactory\Core\Models\AppLookup;
use DreamFactory\Core\Models\AppToAppGroup;
use DreamFactory\Core\Models\DbTableExtras;
use DreamFactory\Core\Models\EmailTemplate;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Models\EventSubscriber;
use DreamFactory\Core\Models\Lookup;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\RoleLookup;
use DreamFactory\Core\Models\RoleServiceAccess;
use DreamFactory\Core\Models\ScriptType;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\ServiceDoc;
use DreamFactory\Core\Models\ServiceType;
use DreamFactory\Core\Models\Setting;
use DreamFactory\Core\Models\SystemResource;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\UserAppRole;
use DreamFactory\Core\Models\UserLookup;

class DbTableExtrasSeeder extends BaseModelSeeder
{
    protected $modelClass = DbTableExtras::class;

    protected $recordIdentifier = ['service_id','table'];

    protected $records = [
        [
            'table' => 'user',
            'model' => User::class,
        ],
        [
            'table' => 'user_lookup',
            'model' => UserLookup::class,
        ],
        [
            'table' => 'user_to_app_to_role',
            'model' => UserAppRole::class,
        ],
        [
            'table' => 'service',
            'model' => Service::class,
        ],
        [
            'table' => 'service_type',
            'model' => ServiceType::class,
        ],
        [
            'table' => 'service_doc',
            'model' => ServiceDoc::class,
        ],
        [
            'table' => 'role',
            'model' => Role::class,
        ],
        [
            'table' => 'role_service_access',
            'model' => RoleServiceAccess::class,
        ],
        [
            'table' => 'role_lookup',
            'model' => RoleLookup::class,
        ],
        [
            'table' => 'app',
            'model' => App::class,
        ],
        [
            'table' => 'app_lookup',
            'model' => AppLookup::class,
        ],
        [
            'table' => 'app_group',
            'model' => AppGroup::class,
        ],
        [
            'table' => 'app_to_app_group',
            'model' => AppToAppGroup::class
        ],
        [
            'table' => 'system_resource',
            'model' => SystemResource::class,
        ],
        [
            'table' => 'script_type',
            'model' => ScriptType::class,
        ],
        [
            'table' => 'event_script',
            'model' => EventScript::class,
        ],
        [
            'table' => 'event_subscriber',
            'model' => EventSubscriber::class,
        ],
        [
            'table' => 'email_template',
            'model' => EmailTemplate::class,
        ],
        [
            'table' => 'system_setting',
            'model' => Setting::class,
        ],
        [
            'table' => 'system_lookup',
            'model' => Lookup::class,
        ]
    ];

    protected function getRecordExtras()
    {
        $systemServiceId = Service::whereType('system')->value('id');

        return [
            'service_id' => $systemServiceId,
        ];
    }
}
