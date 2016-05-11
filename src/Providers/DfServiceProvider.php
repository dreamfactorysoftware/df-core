<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Database\DatabaseServiceProvider;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Models\SystemTableModelMapper;
use DreamFactory\Core\Resources\System\SystemResourceManager;
use DreamFactory\Core\Scripting\ScriptingServiceProvider;
use DreamFactory\Core\Services\ServiceManager;
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
        \App::register(DatabaseServiceProvider::class);

        // Add our scripting drivers.
        \App::register(ScriptingServiceProvider::class);

        // Add our subscription-based services.
        \App::register(SubscriptionServiceProvider::class);

        \Event::subscribe(new ServiceEventHandler());

        // Add conditional providers here.
        $names =
            [
                'ADLdap',
                'Aws',
                'Azure',
                'CouchDb',
                'IbmDb2',
                'MongoDb',
                'OAuth',
                'Oracle',
                'Rackspace',
                'Rws',
                'Salesforce',
                'Soap',
                'SqlAnywhere',
                'SqlDb',
                'SqlSrv',
                'User'
            ];
        foreach ($names as $name){
            $space = "DreamFactory\\Core\\$name\\ServiceProvider";
            if (class_exists($space)) {
                \App::register($space);
            }
        }
    }
}
