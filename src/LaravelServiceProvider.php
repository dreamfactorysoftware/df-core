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
use DreamFactory\Core\Facades\DbSchemaExtensions as DbSchemaExtensionsFacade;
use DreamFactory\Core\Facades\ServiceManager as ServiceManagerFacade;
use DreamFactory\Core\Facades\SystemTableModelMapper as SystemTableModelMapperFacade;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Http\Middleware\AccessCheck;
use DreamFactory\Core\Http\Middleware\AuthCheck;
use DreamFactory\Core\Http\Middleware\FirstUserCheck;
use DreamFactory\Core\Http\Middleware\VerbOverrides;
use DreamFactory\Core\Models\SystemTableModelMapper;
use DreamFactory\Core\Providers\CorsServiceProvider;
use DreamFactory\Core\Services\ServiceManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use MongoDB\Laravel\MongoDBServiceProvider;

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

        $this->addMiddleware();

        $this->registerOtherProviders();

        // add routes
        /** @noinspection PhpUndefinedMethodInspection */
        if (!$this->app->routesAreCached()) {
            include __DIR__ . '/../routes/routes.php';
        }

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
        // Register MongoDB provider first
        $this->app->register(MongoDBServiceProvider::class);

        // merge in df config, https://laravel.com/docs/5.4/packages#resources
        $this->mergeConfigFrom(__DIR__ . '/../config/df.php', 'df');

        $this->registerServices();
        $this->registerExtensions();
    }

    protected function addAliases()
    {
        $this->app->alias('df.service', ServiceManager::class);
        $this->app->alias('df.system.table_model_map', SystemTableModelMapper::class);
        $this->app->alias('df.db.schema', DbSchemaExtensions::class);

        // DreamFactory Specific Facades...
        $loader = AliasLoader::getInstance();
        $loader->alias('ServiceManager', ServiceManagerFacade::class);
        $loader->alias('SystemTableModelMapper', SystemTableModelMapperFacade::class);
        $loader->alias('DbSchemaExtensions', DbSchemaExtensionsFacade::class);
    }

    /**
     * Register any middleware aliases.
     *
     * @return void
     */
    protected function addMiddleware()
    {
        Route::aliasMiddleware('df.auth_check', AuthCheck::class);
        Route::aliasMiddleware('df.access_check', AccessCheck::class);
        Route::aliasMiddleware('df.verb_override', VerbOverrides::class);

        /** Add the first user check to the web group */
        Route::prependMiddlewareToGroup('web', FirstUserCheck::class);

        $middleware = [
            'df.verb_override',
            'df.auth_check',
            'df.access_check'
        ];

        if (Route::hasMiddlewareGroup('df.api')) {
            $apiMiddleware = Arr::get(Route::getMiddlewareGroups(), 'df.api');
            $middleware = array_merge($middleware, $apiMiddleware);
        }
        Route::middlewareGroup('df.api', $middleware);
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

        // The system table-model mapper is used to resolve various system tables to models.
        // It also implements the resolver interface which may be used by other components adding system table mappings.
        $this->app->singleton('df.system.table_model_map', function ($app) {
            return new SystemTableModelMapper($app);
        });

        // The database schema extension manager is used to resolve various database schema extensions.
        // It also implements the resolver interface which may be used by other components adding schema extensions.
        $this->app->singleton('df.db.schema', function ($app) {
            return new DbSchemaExtensions($app);
        });
    }

    protected function registerExtensions()
    {
        // Add our database drivers.
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('sqlite', function ($config, $name) {
                $config = Arr::add($config, 'name', $name);
                $connector = new SQLiteConnector();
                $connection = $connector->connect($config);

                return new SQLiteConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });
    }

    protected function registerOtherProviders()
    {
        // use CORS
        $this->app->register(CorsServiceProvider::class);
    }
}
