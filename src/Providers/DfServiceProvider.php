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
        \App::register(DfCorsServiceProvider::class);

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

        \Event::subscribe(new ServiceEventHandler());

        // Add conditional providers here.
        if (class_exists('DreamFactory\Core\ADLdap\ServiceProvider')) {
            \App::register('DreamFactory\Core\ADLdap\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\Aws\ServiceProvider')) {
            \App::register('DreamFactory\Core\Aws\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\Azure\ServiceProvider')) {
            \App::register('DreamFactory\Core\Azure\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\CouchDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\CouchDb\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\MongoDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\MongoDb\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\OAuth\ServiceProvider')) {
            \App::register('DreamFactory\Core\OAuth\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\Rackspace\ServiceProvider')) {
            \App::register('DreamFactory\Core\Rackspace\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\Rws\ServiceProvider')) {
            \App::register('DreamFactory\Core\Rws\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\Salesforce\ServiceProvider')) {
            \App::register('DreamFactory\Core\Salesforce\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\Soap\ServiceProvider')) {
            \App::register('DreamFactory\Core\Soap\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\SqlDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\SqlDb\ServiceProvider');
        }
        if (class_exists('DreamFactory\Core\User\ServiceProvider')) {
            \App::register('DreamFactory\Core\User\ServiceProvider');
        }
    }
}
