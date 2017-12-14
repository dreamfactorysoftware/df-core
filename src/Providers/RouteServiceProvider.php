<?php

namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Http\Middleware\AccessCheck;
use DreamFactory\Core\Http\Middleware\AuthCheck;
use DreamFactory\Core\Http\Middleware\FirstUserCheck;
use DreamFactory\Core\Http\Middleware\VerbOverrides;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Request;
use Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'DreamFactory\Core\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->addMiddleware();

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {

        Route::prefix(config('df.api_route_prefix', 'api'))
            ->middleware('df.api')
            ->namespace($this->namespace)
            ->group(__DIR__ . '/../../routes/api.php');

        Route::prefix(config('df.status_route_prefix', 'status'))
            ->namespace($this->namespace)
            // TODO: Investigate a better way to add this middleware since df.cors is defined in CorsServiceProvicer.php
            ->middleware('df.cors')
            ->group(__DIR__ . '/../../routes/status.php');

        Route::prefix(config('df.storage_route_prefix'))
            ->namespace($this->namespace)
            // TODO: Investigate a better way to add this middleware since df.cors is defined in CorsServiceProvicer.php
            ->middleware('df.cors')
            ->group(__DIR__ . '/../../routes/storage.php');
    }

    /**
     * Register any middleware aliases.
     *
     * @return void
     */
    protected function addMiddleware()
    {
        // the method name was changed in Laravel 5.4
        if (method_exists(\Illuminate\Routing\Router::class, 'aliasMiddleware')) {
            Route::aliasMiddleware('df.auth_check', AuthCheck::class);
            Route::aliasMiddleware('df.access_check', AccessCheck::class);
            Route::aliasMiddleware('df.verb_override', VerbOverrides::class);
        } else {
            /** @noinspection PhpUndefinedMethodInspection */
            Route::middleware('df.auth_check', AuthCheck::class);
            /** @noinspection PhpUndefinedMethodInspection */
            Route::middleware('df.access_check', AccessCheck::class);
            Route::middleware('df.verb_override', VerbOverrides::class);
        }

        /** Add the first user check to the web group */
        Route::prependMiddlewareToGroup('web', FirstUserCheck::class);

        Route::middlewareGroup('df.api', [
            'df.verb_override',
            'df.auth_check',
            'df.access_check'
        ]);
    }
}
