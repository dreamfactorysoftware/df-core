<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Components\ServiceFactory;
use DreamFactory\Core\Components\ServiceManager;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
{
    public function register()
    {
        \App::register(DfCorsServiceProvider::class);

        // If oauth required, add provider here.
        if (class_exists('Laravel\Socialite\SocialiteServiceProvider')) {
            \App::register('Laravel\Socialite\SocialiteServiceProvider');
        }

        // The service factory is used to create the actual service instances.
        $this->app->singleton('df.service.factory', function ($app) {
            return new ServiceFactory($app);
        });
        
        // The service manager is used to resolve various services, since multiple
        // services might be managed. It also implements the service resolver
        // interface which may be used by other components requiring services.
        $this->app->singleton('df.service', function ($app) {
            return new ServiceManager($app, $app['df.service.factory']);
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
    }
}
