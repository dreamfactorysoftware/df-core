<?php

namespace DreamFactory\Core\Models;

class SystemTableModelMapper
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The map holding table name to model class name.
     *
     * @var array[]
     */
    protected $map = [];

    /**
     * Create a new system resource manager instance.
     *
     * @param  \Illuminate\Foundation\Application $app
     */
    public function __construct($app)
    {
        $this->app = $app;
        $this->map = [
            'app'                 => App::class,
            'email_template'      => EmailTemplate::class,
            'lookup'              => BaseSystemLookup::class,
            'role'                => Role::class,
            'role_service_access' => RoleServiceAccess::class,
            'service'             => Service::class,
            'service_doc'         => ServiceDoc::class,
            'system_custom'       => SystemCustom::class,
            'user'                => User::class,
            'user_to_app_to_role' => UserAppRole::class,
        ];
    }

    /**
     * Register a system resource type extension resolver.
     *
     * @param string $table
     * @param string $model
     *
     */
    public function addMapping($table, $model)
    {
        $this->map[$table] = $model;
    }

    /**
     * Return the model for the given table.
     *
     * @param string $table
     *
     * @return string
     */
    public function getModel($table)
    {
        if (isset($this->map[$table])) {
            return $this->map[$table];
        }

        return null;
    }

    /**
     * Return the table for the given model.
     *
     * @param string $model
     *
     * @return string
     */
    public function getTable($model)
    {
        if (false !== $pos = array_search($model, $this->map)) {
            return $pos;
        }

        return null;
    }

    /**
     * Return all mappings.
     *
     * @return array
     */
    public function getMap()
    {
        return $this->map;
    }
}
