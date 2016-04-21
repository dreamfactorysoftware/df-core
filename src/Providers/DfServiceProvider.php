<?php
namespace DreamFactory\Core\Providers;

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

        \Event::subscribe(new ServiceEventHandler());

        // Add our database drivers.
        \App::register(DfSqlDbServiceProvider::class);

        // If mongo required, add provider here.
        if (class_exists('DreamFactory\Core\MongoDb\ServiceProvider')) {
            \App::register('DreamFactory\Core\MongoDb\ServiceProvider');
        }
    }
}
