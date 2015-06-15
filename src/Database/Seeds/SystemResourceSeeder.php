<?php
namespace DreamFactory\Core\Database\Seeds;

class SystemResourceSeeder extends BaseModelSeeder
{
    protected $modelClass = 'DreamFactory\\Core\\Models\\SystemResource';

    protected $records = [
        [
            'name'        => 'admin',
            'label'       => 'Administrators',
            'description' => 'Allows configuration of system administrators.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Admin',
            'model_name'  => 'DreamFactory\\Core\\Models\\User',
        ],
        [
            'name'        => 'cache',
            'label'       => 'Cache Administration',
            'description' => 'Allows administration of system-wide and service cache.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Cache'
        ],
        [
            'name'        => 'config',
            'label'       => 'Configuration',
            'description' => 'Global system configuration.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Config',
            'singleton'   => true,
        ],
        [
            'name'        => 'constant',
            'label'       => 'Constants',
            'description' => 'Read-only listing of constants available for client use.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Constant',
            'read_only'   => true,
        ],
        [
            'name'        => 'cors',
            'label'       => 'CORS Configuration',
            'description' => 'Allows configuration of CORS system settings.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Cors',
            'model_name'  => 'DreamFactory\\Core\\Models\\CorsConfig',
        ],
        [
            'name'        => 'email_template',
            'label'       => 'Email Templates',
            'description' => 'Allows configuration of email templates.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\EmailTemplate',
            'model_name'  => 'DreamFactory\\Core\\Models\\EmailTemplate',
        ],
        [
            'name'        => 'environment',
            'label'       => 'Environment',
            'description' => 'Read-only system environment configuration.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Environment',
            'singleton'   => true,
            'read_only'   => true,
        ],
        [
            'name'        => 'event',
            'label'       => 'Events',
            'description' => 'Allows registering server-side scripts to system generated events.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Event',
        ],
        [
            'name'        => 'lookup',
            'label'       => 'Lookup Keys',
            'description' => 'Allows configuration of lookup keys.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Lookup',
            'model_name'  => 'DreamFactory\\Core\\Models\\Lookup',
        ],
        [
            'name'        => 'role',
            'label'       => 'Roles',
            'description' => 'Allows role configuration.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Role',
            'model_name'  => 'DreamFactory\\Core\\Models\\Role',
        ],
        [
            'name'        => 'service',
            'label'       => 'Services',
            'description' => 'Allows configuration of services.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Service',
            'model_name'  => 'DreamFactory\\Core\\Models\\Service',
        ],
        [
            'name'        => 'service_type',
            'label'       => 'Service Types',
            'description' => 'Read-only system service types.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\ServiceType',
            'model_name'  => 'DreamFactory\\Core\\Models\\ServiceType',
            'read_only'   => true,
        ],
        [
            'name'        => 'script_type',
            'label'       => 'Script Types',
            'description' => 'Read-only system scripting types.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\ScriptType',
            'model_name'  => 'DreamFactory\\Core\\Models\\ScriptType',
            'read_only'   => true,
        ],
        [
            'name'        => 'setting',
            'label'       => 'Custom Settings',
            'description' => 'Allows configuration of system-wide custom settings.',
            'class_name'  => 'DreamFactory\\Core\\Resources\\System\\Setting',
            'model_name'  => 'DreamFactory\\Core\\Models\\Setting',
        ],
        [
            'name'        => 'app',
            'label'       => 'Apps',
            'description' => 'Allows management of user application(s)',
            'class_name'  => "DreamFactory\\Core\\Resources\\System\\App",
            'model_name'  => 'DreamFactory\\Core\\Models\\App',
        ],
        [
            'name'        => 'app_group',
            'label'       => 'App Groups',
            'description' => 'Allows grouping of user application(s)',
            'class_name'  => "DreamFactory\\Core\\Resources\\System\\AppGroup",
            'model_name'  => 'DreamFactory\\Core\\Models\\AppGroup',
        ],
    ];
}
