<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Components\DbSchemaExtensions;
use DreamFactory\Core\Database\Connectors\SQLiteConnector;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Models\SystemTableModelMapper;
use DreamFactory\Core\Resources\System\SystemResourceManager;
use DreamFactory\Core\Services\ServiceManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
{
    public function register()
    {
        \App::register(CorsServiceProvider::class);

        // The service manager is used to resolve various services and service types.
        // It also implements the resolver interface which may be used by other components adding service types.
        $this->app->singleton('df.service', function ($app){
            return new ServiceManager($app);
        });

        // The system resource manager is used to resolve various system resource types.
        // It also implements the resolver interface which may be used by other components adding system resource types.
        $this->app->singleton('df.system.resource', function ($app){
            return new SystemResourceManager($app);
        });

        // The system table-model mapper is used to resolve various system tables to models.
        // It also implements the resolver interface which may be used by other components adding system table mappings.
        $this->app->singleton('df.system.table_model_map', function ($app){
            return new SystemTableModelMapper($app);
        });

        // Add our database drivers.
        $this->app->resolving('db', function (DatabaseManager $db){
            $db->extend('sqlite', function ($config){
                $connector = new SQLiteConnector();
                $connection = $connector->connect($config);

                return new SQLiteConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });

        // The database schema extension manager is used to resolve various database schema extensions.
        // It also implements the resolver interface which may be used by other components adding schema extensions.
        $this->app->singleton('db.schema', function ($app){
            return new DbSchemaExtensions($app);
        });

        // Add our subscription-based services.
        \App::register(SubscriptionServiceProvider::class);

        \Event::subscribe(new ServiceEventHandler());

        // Add conditional providers here.
        $names =
            [
                'ADLdap',
                'Aws',
                'Azure',
                'AzureAD',
                'Cache',
                'Cassandra',
                'Couchbase',
                'CouchDb',
                'Database',
                'Email',
                'IbmDb2',
                'Limit',
                'Logger',
                'MongoDb',
                'OAuth',
                'Oracle',
                'Rackspace',
                'Rws',
                'Salesforce',
                'Saml',
                'Script',
                'Soap',
                'SqlAnywhere',
                'SqlDb',
                'SqlSrv',
                'User'
            ];
        foreach ($names as $name) {
            $space = "DreamFactory\\Core\\$name\\ServiceProvider";
            if (class_exists($space)) {
                \App::register($space);
            }
        }
    }
}
