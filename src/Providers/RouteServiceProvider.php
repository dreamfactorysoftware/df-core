<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Http\Middleware\AccessCheck;
use DreamFactory\Core\Http\Middleware\AuthCheck;
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
        if (env('DF_MANAGED', false)) {
            /*
             * Controller route to allow the Enterprise Console to talk to instances.
             * If this route is removed or disabled Enterprise functions will break
             */
            // todo this needs to be upgraded to work
            //Route::controller('/instance', '\DreamFactory\Managed\Http\Controllers\InstanceController');
        }

        /* Check for verb tunneling by the various method override headers or query params
         * Tunnelling verb overrides:
         *      X-Http-Method (Microsoft)
         *      X-Http-Method-Override (Google/GData)
         *      X-Method-Override (IBM)
         * Symfony natively supports X-HTTP-METHOD-OVERRIDE header and "_method" URL parameter
         * we just need to add our historical support for other options, including "method" URL parameter
         */
        Request::enableHttpMethodParameterOverride(); // enables _method URL parameter
        $method = Request::getMethod();
        if (('POST' === $method) &&
            (!empty($dfOverride = Request::header('X-HTTP-Method',
                Request::header('X-Method-Override', Request::query('method')))))
        ) {
            Request::setMethod($method = strtoupper($dfOverride));
        }
        // support old MERGE as PATCH
        if ('MERGE' === strtoupper($method)) {
            Request::setMethod('PATCH');
        }

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
        } else {
            /** @noinspection PhpUndefinedMethodInspection */
            Route::middleware('df.auth_check', AuthCheck::class);
            /** @noinspection PhpUndefinedMethodInspection */
            Route::middleware('df.access_check', AccessCheck::class);
        }

        Route::middlewareGroup('df.api', ['df.auth_check', 'df.access_check']);
    }
}
