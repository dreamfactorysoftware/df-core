<?php
namespace DreamFactory\Core\Database\Seeds;

use DreamFactory\Core\Models\CorsConfig;
use DreamFactory\Core\Models\SystemCustom;
use DreamFactory\Core\Models\SystemResource;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Resources\System\Admin;
use DreamFactory\Core\Resources\System\App;
use DreamFactory\Core\Resources\System\AppGroup;
use DreamFactory\Core\Resources\System\Cache;
use DreamFactory\Core\Resources\System\Config;
use DreamFactory\Core\Resources\System\Constant;
use DreamFactory\Core\Resources\System\Cors;
use DreamFactory\Core\Resources\System\Custom;
use DreamFactory\Core\Resources\System\EmailTemplate;
use DreamFactory\Core\Resources\System\Environment;
use DreamFactory\Core\Resources\System\Event;
use DreamFactory\Core\Resources\System\Lookup;
use DreamFactory\Core\Resources\System\Role;
use DreamFactory\Core\Resources\System\ScriptType;
use DreamFactory\Core\Resources\System\Service;
use DreamFactory\Core\Resources\System\ServiceType;
use DreamFactory\Core\Resources\System\Setting;

class SystemResourceSeeder extends BaseModelSeeder
{
    protected $modelClass = SystemResource::class;

    protected $records = [
        [
            'name'        => 'admin',
            'label'       => 'Administrators',
            'description' => 'Allows configuration of system administrators.',
            'class_name'  => Admin::class,
            'model_name'  => User::class,
        ],
        [
            'name'        => 'cache',
            'label'       => 'Cache Administration',
            'description' => 'Allows administration of system-wide and service cache.',
            'class_name'  => Cache::class
        ],
        [
            'name'        => 'config',
            'label'       => 'Configuration',
            'description' => 'Global system configuration.',
            'class_name'  => Config::class,
            'singleton'   => true,
        ],
        [
            'name'        => 'constant',
            'label'       => 'Constants',
            'description' => 'Read-only listing of constants available for client use.',
            'class_name'  => Constant::class,
            'read_only'   => true,
        ],
        [
            'name'        => 'cors',
            'label'       => 'CORS Configuration',
            'description' => 'Allows configuration of CORS system settings.',
            'class_name'  => Cors::class,
            'model_name'  => CorsConfig::class,
        ],
        [
            'name'        => 'email_template',
            'label'       => 'Email Templates',
            'description' => 'Allows configuration of email templates.',
            'class_name'  => EmailTemplate::class,
            'model_name'  => \DreamFactory\Core\Models\EmailTemplate::class,
        ],
        [
            'name'        => 'environment',
            'label'       => 'Environment',
            'description' => 'Read-only system environment configuration.',
            'class_name'  => Environment::class,
            'singleton'   => true,
            'read_only'   => true,
        ],
        [
            'name'        => 'event',
            'label'       => 'Events',
            'description' => 'Allows registering server-side scripts to system generated events.',
            'class_name'  => Event::class,
        ],
        [
            'name'        => 'lookup',
            'label'       => 'Lookup Keys',
            'description' => 'Allows configuration of lookup keys.',
            'class_name'  => Lookup::class,
            'model_name'  => \DreamFactory\Core\Models\Lookup::class,
        ],
        [
            'name'        => 'role',
            'label'       => 'Roles',
            'description' => 'Allows role configuration.',
            'class_name'  => Role::class,
            'model_name'  => \DreamFactory\Core\Models\Role::class,
        ],
        [
            'name'        => 'service',
            'label'       => 'Services',
            'description' => 'Allows configuration of services.',
            'class_name'  => Service::class,
            'model_name'  => \DreamFactory\Core\Models\Service::class,
        ],
        [
            'name'        => 'service_type',
            'label'       => 'Service Types',
            'description' => 'Read-only system service types.',
            'class_name'  => ServiceType::class,
            'model_name'  => \DreamFactory\Core\Models\ServiceType::class,
            'read_only'   => true,
        ],
        [
            'name'        => 'script_type',
            'label'       => 'Script Types',
            'description' => 'Read-only system scripting types.',
            'class_name'  => ScriptType::class,
            'model_name'  => \DreamFactory\Core\Models\ScriptType::class,
            'read_only'   => true,
        ],
        [
            'name'        => 'setting',
            'label'       => 'Custom Settings',
            'description' => 'Allows configuration of system-wide custom settings.',
            'class_name'  => Setting::class,
            'model_name'  => \DreamFactory\Core\Models\Setting::class,
        ],
        [
            'name'        => 'app',
            'label'       => 'Apps',
            'description' => 'Allows management of user application(s)',
            'class_name'  => App::class,
            'model_name'  => \DreamFactory\Core\Models\App::class,
        ],
        [
            'name'        => 'app_group',
            'label'       => 'App Groups',
            'description' => 'Allows grouping of user application(s)',
            'class_name'  => AppGroup::class,
            'model_name'  => \DreamFactory\Core\Models\AppGroup::class,
        ],
        [
            'name'        => 'custom',
            'label'       => 'Custom Settings',
            'description' => 'Allows for creating system-wide custom settings',
            'class_name'  => Custom::class,
            'model_name'  => SystemCustom::class
        ]
    ];
}
