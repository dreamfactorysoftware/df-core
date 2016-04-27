<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\Service;
use InvalidArgumentException;

class ServiceManager
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    /**
     * The service factory instance.
     *
     * @var ServiceFactory
     */
    protected $factory;

    /**
     * The active service instances.
     *
     * @var array
     */
    protected $services = [];

    /**
     * The custom service resolvers.
     *
     * @var array
     */
    protected $extensions = [];

    /**
     * Create a new service manager instance.
     *
     * @param  \Illuminate\Foundation\Application $app
     * @param  ServiceFactory                     $factory
     */
    public function __construct($app, ServiceFactory $factory)
    {
        $this->app = $app;
        $this->factory = $factory;
    }

    /**
     * Get a database connection instance.
     *
     * @param  string  $name
     * @return \Illuminate\Database\Connection
     */
    public function connection($name)
    {
        // If we haven't created this service, we'll create it based on the config provided.
        if (! isset($this->services[$name])) {
            $connection = $this->makeService($name);

//            if ($this->app->bound('events')) {
//                $connection->setEventDispatcher($this->app['events']);
//            }

            $this->services[$name] = $connection;
        }

        return $this->services[$name];
    }

    /**
     * Disconnect from the given service and remove from local cache.
     *
     * @param  string  $name
     * @return void
     */
    public function purge($name)
    {
        $this->disconnect($name);

        unset($this->services[$name]);
    }

    /**
     * Disconnect from the given service.
     *
     * @param  string  $name
     * @return void
     */
    public function disconnect($name)
    {
        if (isset($this->services[$name])) {
            $this->services[$name]->disconnect();
        }
    }

    /**
     * Make the database connection instance.
     *
     * @param  string  $name
     * @return \Illuminate\Database\Connection
     */
    protected function makeService($name)
    {
        $config = $this->getConfig($name);

        // First we will check by the service name to see if an extension has been
        // registered specifically for that service. If it has we will call the
        // Closure and pass it the config allowing it to resolve the service.
        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        $type = $config['type'];

        // Next we will check to see if an extension has been registered for a service type
        // and will call the Closure if so, which allows us to have a more generic
        // resolver for the service types themselves which applies to all services.
        if (isset($this->extensions[$type])) {
            return call_user_func($this->extensions[$type], $config, $name);
        }

        return $this->factory->make($config, $name);
    }

    /**
     * Get the configuration for a connection.
     *
     * @param  string  $name
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function getConfig($name)
    {
        if (empty($name)) {
            throw new InvalidArgumentException("Service 'name' can not be empty.");
        }

        $config = Service::getCachedByName($name);

        return $config;
    }

    /**
     * Register an extension service resolver.
     *
     * @param  string    $name
     * @param  callable  $resolver
     * @return void
     */
    public function extend($name, callable $resolver)
    {
        $this->extensions[$name] = $resolver;
    }

    /**
     * Return all of the created services.
     *
     * @return array
     */
    public function getServices()
    {
        return $this->services;
    }
}
