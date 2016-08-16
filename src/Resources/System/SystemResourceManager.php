<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\SystemResourceTypeInterface;

class SystemResourceManager
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The custom system resource type information.
     *
     * @var SystemResourceTypeInterface[]
     */
    protected $types = [];

    /**
     * Create a new system resource manager instance.
     *
     * @param  \Illuminate\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $types = [
            [
                'name'        => 'admin',
                'label'       => 'Administrators',
                'description' => 'Allows configuration of system administrators.',
                'class_name'  => Admin::class,
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
            ],
            [
                'name'        => 'email_template',
                'label'       => 'Email Templates',
                'description' => 'Allows configuration of email templates.',
                'class_name'  => EmailTemplate::class,
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
                'description' => 'Provides a list of system generated events.',
                'class_name'  => Event::class,
            ],
            [
                'name'        => 'event_script',
                'label'       => 'Event Scripts',
                'description' => 'Allows registering server-side scripts to system generated events.',
                'class_name'  => EventScript::class,
            ],
            [
                'name'        => 'lookup',
                'label'       => 'Lookup Keys',
                'description' => 'Allows configuration of lookup keys.',
                'class_name'  => Lookup::class,
            ],
            [
                'name'        => 'role',
                'label'       => 'Roles',
                'description' => 'Allows role configuration.',
                'class_name'  => Role::class,
            ],
            [
                'name'        => 'service',
                'label'       => 'Services',
                'description' => 'Allows configuration of services.',
                'class_name'  => Service::class,
            ],
            [
                'name'        => 'service_type',
                'label'       => 'Service Types',
                'description' => 'Read-only system service types.',
                'class_name'  => ServiceType::class,
                'read_only'   => true,
            ],
            [
                'name'        => 'script_type',
                'label'       => 'Script Types',
                'description' => 'Read-only system scripting types.',
                'class_name'  => ScriptType::class,
                'read_only'   => true,
            ],
            [
                'name'        => 'app',
                'label'       => 'Apps',
                'description' => 'Allows management of user application(s)',
                'class_name'  => App::class,
            ],
            [
                'name'        => 'app_group',
                'label'       => 'App Groups',
                'description' => 'Allows grouping of user application(s)',
                'class_name'  => AppGroup::class,
            ],
            [
                'name'        => 'custom',
                'label'       => 'Custom Settings',
                'description' => 'Allows for creating system-wide custom settings',
                'class_name'  => Custom::class,
            ],
            [
                'name'        => 'package',
                'label'       => 'Package',
                'description' => 'Allows Package import/export',
                'class_name'  => Package::class
            ]
        ];
        foreach ($types as $type) {
            $this->addType(new SystemResourceType($type));
        }
    }

    /**
     * Register a system resource type extension resolver.
     *
     * @param  SystemResourceTypeInterface|null $type
     *
     * @return void
     */
    public function addType(SystemResourceTypeInterface $type)
    {
        $this->types[$type->getName()] = $type;
    }

    /**
     * Return the service type info.
     *
     * @param string $name
     *
     * @return SystemResourceTypeInterface
     */
    public function getResourceType($name)
    {
        if (isset($this->types[$name])) {
            return $this->types[$name];
        }

        return null;
    }

    /**
     * Return all of the known service types.
     *
     * @return SystemResourceTypeInterface[]
     */
    public function getResourceTypes()
    {
        return $this->types;
    }
}
