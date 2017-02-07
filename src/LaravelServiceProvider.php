<?php
namespace DreamFactory\Core;

use DreamFactory\Core\Commands\ClearAllFileCache;
use DreamFactory\Core\Commands\HomesteadConfig;
use DreamFactory\Core\Commands\Import;
use DreamFactory\Core\Commands\ImportPackage;
use DreamFactory\Core\Commands\Request;
use DreamFactory\Core\Commands\Setup;
use DreamFactory\Core\Components\DbSchemaExtensions;
use DreamFactory\Core\Database\Connectors\SQLiteConnector;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Facades\DbSchemaExtensions as DbSchemaExtensionsFacade;
use DreamFactory\Core\Facades\ServiceManager as ServiceManagerFacade;
use DreamFactory\Core\Facades\SystemResourceManager as SystemResourceManagerFacade;
use DreamFactory\Core\Facades\SystemTableModelMapper as SystemTableModelMapperFacade;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Models\SystemTableModelMapper;
use DreamFactory\Core\Providers\CorsServiceProvider;
use DreamFactory\Core\Providers\RouteServiceProvider;
use DreamFactory\Core\Resources\System\SystemResourceManager;
use DreamFactory\Core\Services\ServiceManager;
use DreamFactory\Core\Services\ServiceType;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\ServiceProvider;
use Event;

class LaravelServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     */
    public function boot()
    {
        // add our df config
        $configPath = __DIR__ . '/../config/config.php';
        if (function_exists('config_path')) {
            $publishPath = config_path('df.php');
        } else {
            $publishPath = base_path('config/df.php');
        }
        $this->publishes([$configPath => $publishPath], 'config');

        $this->addAliases();

        // add commands, https://laravel.com/docs/5.4/packages#commands
        /** @noinspection PhpUndefinedMethodInspection */
        if ($this->app->runningInConsole()) {
            $this->addCommands();
        }

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
        $configPath = __DIR__ . '/../config/config.php';
        $this->mergeConfigFrom($configPath, 'df');

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
        $loader->alias('ServiceManager', ServiceManagerFacade::class);
        $loader->alias('SystemResourceManager', SystemResourceManagerFacade::class);
        $loader->alias('SystemTableModelMapper', SystemTableModelMapperFacade::class);
        $loader->alias('DbSchemaExtensions', DbSchemaExtensionsFacade::class);
    }

    protected function addCommands()
    {
        $this->commands([
            ClearAllFileCache::class,
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

        // Add conditional service providers here or
        // Add DreamFactory subscription-based service types for advertising
        $packages = [
            'ADLdap'      => [
                [
                    'name'        => 'adldap',
                    'label'       => 'Active Directory',
                    'description' => 'A service for supporting Active Directory integration',
                    'group'       => ServiceTypeGroups::LDAP,
                ],
                [
                    'name'        => 'ldap',
                    'label'       => 'Standard LDAP',
                    'description' => 'A service for supporting Open LDAP integration',
                    'group'       => ServiceTypeGroups::LDAP,
                ],
            ],
            'Aws'         => [],
            'Azure'       => [],
            'AzureAD'     => [
                [
                    'name'        => 'oauth_azure_ad',
                    'label'       => 'Azure Active Directory OAuth',
                    'description' => 'OAuth service for supporting Azure Active Directory authentication and API access.',
                    'group'       => ServiceTypeGroups::OAUTH,
                ],
            ],
            'Cache'       => [],
            'Cassandra'   => [],
            'Couchbase'   => [],
            'CouchDb'     => [],
            'Database'    => [],
            'Email'       => [],
            'IbmDb2'      => [
                [
                    'name'        => 'ibmdb2',
                    'label'       => 'IBM DB2',
                    'description' => 'Database service supporting IBM DB2 SQL connections.',
                    'group'       => ServiceTypeGroups::DATABASE,
                ],
            ],
            'Limit'      => [
                [
                    'name'        => 'limit',
                    'label'       => 'limit',
                    'description' => 'API rate limiting service.',
                    'group'       => ServiceTypeGroups::LIMIT,
                ],
            ],
            'Logger'      => [
                [
                    'name'        => 'logstash',
                    'label'       => 'Logstash',
                    'description' => 'Log service supporting Logstash.',
                    'group'       => ServiceTypeGroups::LOG,
                ],
            ],
            'MongoDb'     => [],
            'OAuth'       => [],
            'Oracle'      => [
                [
                    'name'        => 'oracle',
                    'label'       => 'Oracle',
                    'description' => 'Database service supporting SQL connections.',
                    'group'       => ServiceTypeGroups::DATABASE,
                ],
            ],
            'Rackspace'   => [],
            'Rws'         => [],
            'Salesforce'  => [
                [
                    'name'        => 'salesforce_db',
                    'label'       => 'Salesforce',
                    'description' => 'Database service for Salesforce connections.',
                    'group'       => ServiceTypeGroups::DATABASE,
                ],
            ],
            'Saml'        => [
                [
                    'name'        => 'saml',
                    'label'       => 'SAML 2.0',
                    'description' => 'SAML 2.0 service supporting SSO.',
                    'group'       => ServiceTypeGroups::SSO,
                ]
            ],
            'Script'      => [],
            'Soap'        => [
                [
                    'name'        => 'soap',
                    'label'       => 'SOAP Service',
                    'description' => 'A service to handle SOAP Services',
                    'group'       => ServiceTypeGroups::REMOTE,
                ],
            ],
            'SqlAnywhere' => [
                [
                    'name'        => 'sqlanywhere',
                    'label'       => 'SAP SQL Anywhere',
                    'description' => 'Database service supporting SAP SQL Anywhere connections.',
                    'group'       => ServiceTypeGroups::DATABASE,
                ],
            ],
            'SqlDb'       => [],
            'SqlSrv'      => [
                [
                    'name'        => 'sqlsrv',
                    'label'       => 'SQL Server',
                    'description' => 'Database service supporting SQL Server connections.',
                    'group'       => ServiceTypeGroups::DATABASE,
                ],
            ],
            'User'        => [],
        ];
        foreach ($packages as $name => $serviceTypes) {
            $space = "DreamFactory\\Core\\$name\\ServiceProvider";
            if (class_exists($space)) {
                $this->app->register($space);
            } else {
                $this->app->resolving('df.service', function (ServiceManager $df) use ($serviceTypes) {
                    foreach ($serviceTypes as $config) {
                        $config['subscription_required'] = true;
                        $df->addType(new ServiceType($config));
                    }
                });
            }
        }
    }
}
