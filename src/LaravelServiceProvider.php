<?php

namespace DreamFactory\Core;

use DreamFactory\Core\Commands\ClearAllFileCache;
use DreamFactory\Core\Commands\Env;
use DreamFactory\Core\Commands\HomesteadConfig;
use DreamFactory\Core\Commands\Import;
use DreamFactory\Core\Commands\ImportPackage;
use DreamFactory\Core\Commands\Request;
use DreamFactory\Core\Commands\Setup;
use DreamFactory\Core\Components\DbSchemaExtensions;
use DreamFactory\Core\Database\Connectors\SQLiteConnector;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Facades\DbSchemaExtensions as DbSchemaExtensionsFacade;
use DreamFactory\Core\Facades\ServiceManager as ServiceManagerFacade;
use DreamFactory\Core\Facades\SystemResourceManager as SystemResourceManagerFacade;
use DreamFactory\Core\Facades\SystemTableModelMapper as SystemTableModelMapperFacade;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Models\Config;
use DreamFactory\Core\Models\SystemTableModelMapper;
use DreamFactory\Core\Providers\CorsServiceProvider;
use DreamFactory\Core\Providers\RouteServiceProvider;
use DreamFactory\Core\Resources\System\SystemResourceManager;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use DreamFactory\Core\Services\System;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Event;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     */
    public function boot()
    {
        // add our df config
        $configPath = __DIR__ . '/../config/df.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('df.php');
        } else {
            $publishPath = base_path('config/df.php');
        }
        $this->publishes([$configPath => $publishPath], 'config');

        $this->addAliases();

        // add commands, https://laravel.com/docs/5.4/packages#commands
        $this->addCommands();

        // add migrations, https://laravel.com/docs/5.4/packages#resources
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // subscribe to all listened to events
        Event::subscribe(new ServiceEventHandler());
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // merge in df config, https://laravel.com/docs/5.4/packages#resources
        $this->mergeConfigFrom(__DIR__ . '/../config/df.php', 'df');

        $this->registerServices();
        $this->registerExtensions();
        $this->registerOtherProviders();
    }

    protected function addAliases()
    {
        $this->app->alias('df.service', ServiceManager::class);
        $this->app->alias('df.system.resource', SystemResourceManager::class);
        $this->app->alias('df.system.table_model_map', SystemTableModelMapper::class);
        $this->app->alias('db.schema', DbSchemaExtensions::class);

        // DreamFactory Specific Facades...
        $loader = AliasLoader::getInstance();
        $loader->alias('JWTAuth', JWTAuth::class);
        $loader->alias('JWTFactory', JWTFactory::class);
        $loader->alias('ServiceManager', ServiceManagerFacade::class);
        $loader->alias('SystemResourceManager', SystemResourceManagerFacade::class);
        $loader->alias('SystemTableModelMapper', SystemTableModelMapperFacade::class);
        $loader->alias('DbSchemaExtensions', DbSchemaExtensionsFacade::class);
    }

    protected function addCommands()
    {
        $this->commands([
            ClearAllFileCache::class,
            Env::class,
            HomesteadConfig::class,
            Import::class,
            ImportPackage::class,
            Request::class,
            Setup::class,
        ]);
    }

    protected function registerServices()
    {
        // The service manager is used to resolve various services and service types.
        // It also implements the resolver interface which may be used by other components adding service types.
        $this->app->singleton('df.service', function ($app) {
            return new ServiceManager($app);
        });

        // The system resource manager is used to resolve various system resource types.
        // It also implements the resolver interface which may be used by other components adding system resource types.
        $this->app->singleton('df.system.resource', function ($app) {
            return new SystemResourceManager($app);
        });

        // The system table-model mapper is used to resolve various system tables to models.
        // It also implements the resolver interface which may be used by other components adding system table mappings.
        $this->app->singleton('df.system.table_model_map', function ($app) {
            return new SystemTableModelMapper($app);
        });

        // The database schema extension manager is used to resolve various database schema extensions.
        // It also implements the resolver interface which may be used by other components adding schema extensions.
        $this->app->singleton('db.schema', function ($app) {
            return new DbSchemaExtensions($app);
        });

        $this->app->resolving('df.service', function (ServiceManager $df) {
            // Add the system service
            $df->addType(new ServiceType([
                    'name'              => 'system',
                    'label'             => 'System Management',
                    'description'       => 'Service supporting management of the system.',
                    'group'             => ServiceTypeGroups::SYSTEM,
                    'singleton'         => true,
                    'config_handler'    => Config::class,
                    'factory'           => function ($config) {
                        return new System($config);
                    },
                    'access_exceptions' => [
                        [
                            'verb_mask' => 31, //Allow all verbs
                            'resource'  => 'admin/session',
                        ],
                        [
                            'verb_mask' => 2, //Allow POST only
                            'resource'  => 'admin/password',
                        ],
                        [
                            'verb_mask' => 1,
                            'resource'  => 'environment',
                        ],
                        [
                            'verb_mask' => VerbsMask::arrayToMask([Verbs::GET, Verbs::POST]),
                            'resource'  => 'package'
                        ]
                    ],
                ]
            ));
        });
    }

    protected function registerExtensions()
    {
        // Add our database drivers.
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('sqlite', function ($config) {
                $connector = new SQLiteConnector();
                $connection = $connector->connect($config);

                return new SQLiteConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });
    }

    protected function registerOtherProviders()
    {
        // add DreamFactory routes
        $this->app->register(RouteServiceProvider::class);
        // use CORS
        $this->app->register(CorsServiceProvider::class);
        // use JWT instead of sessions
        $this->app->register(\Tymon\JWTAuth\Providers\LaravelServiceProvider::class);
    }
}
