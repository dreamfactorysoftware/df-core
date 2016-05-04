<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Resources\System\SystemResourceManager;
use DreamFactory\Core\Scripting\ScriptEngineManager;
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

        // The script engine manager is used to resolve various script engines.
        // It also implements the resolver interface which may be used by other components adding script engines.
        $this->app->singleton('df.script', function ($app){
            return new ScriptEngineManager($app);
        });

        // The system resource manager is used to resolve various system resource types.
        // It also implements the resolver interface which may be used by other components adding system resource types.
        $this->app->singleton('df.system.resource', function ($app){
            return new SystemResourceManager($app);
        });
        
        \Event::subscribe(new ServiceEventHandler());

        // Add our database drivers.
        \App::register(DfSqlDbServiceProvider::class);

        // If user required, add provider here.
        if (class_exists('DreamFactory\Core\User\ServiceProvider')) {
            \App::register('DreamFactory\Core\User\ServiceProvider');
        }

        // If sqldb required, add provider here.
        if (class_exists('DreamFactory\Core\SqlDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\SqlDb\ServiceProvider');
        }

        // If mongodb required, add provider here.
        if (class_exists('DreamFactory\Core\MongoDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\MongoDb\ServiceProvider');
        }

        // If aws required, add provider here.
        if (class_exists('DreamFactory\Core\Aws\ServiceProvider')) {
            \App::register('DreamFactory\Core\Aws\ServiceProvider');
        }

        // If azure required, add provider here.
        if (class_exists('DreamFactory\Core\Azure\ServiceProvider')) {
            \App::register('DreamFactory\Core\Azure\ServiceProvider');
        }

        // If adldap required, add provider here.
        if (class_exists('DreamFactory\Core\ADLdap\ServiceProvider')) {
            \App::register('DreamFactory\Core\ADLdap\ServiceProvider');
        }

        // If couchdb required, add provider here.
        if (class_exists('DreamFactory\Core\CouchDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\CouchDb\ServiceProvider');
        }

        // If couchdb required, add provider here.
        if (class_exists('DreamFactory\Core\Soap\ServiceProvider')) {
            \App::register('DreamFactory\Core\Soap\ServiceProvider');
        }

        // If couchdb required, add provider here.
        if (class_exists('DreamFactory\Core\Rws\ServiceProvider')) {
            \App::register('DreamFactory\Core\Rws\ServiceProvider');
        }

        // If rackspace required, add provider here.
        if (class_exists('DreamFactory\Core\Rackspace\ServiceProvider')) {
            \App::register('DreamFactory\Core\Rackspace\ServiceProvider');
        }

        // If salesforce required, add provider here.
        if (class_exists('DreamFactory\Core\Salesforce\ServiceProvider')) {
            \App::register('DreamFactory\Core\Salesforce\ServiceProvider');
        }
    }
}
