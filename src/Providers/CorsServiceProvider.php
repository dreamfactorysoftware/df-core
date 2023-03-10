<?php

namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Services\DfCorsService;
use DreamFactory\Core\Models\CorsConfig;
use Fruitcake\Cors\CorsService;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Route;
use Cache;

class CorsServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    public function register()
    {
    }

    /**
     * Add the Cors middleware to the router.
     *
     * @param Request $request
     * @param Kernel  $kernel
     * @throws \Exception
     */
    public function boot(Request $request, Kernel $kernel)
    {
        $api_prefix = config('df.api_route_prefix', 'api');
        config(['cors.paths' => [$api_prefix . '/*']]);
        
        $config = $this->getOptions($request);
        $this->app->singleton(CorsService::class, function () use ($config){
            return new DfCorsService($config);
        });

        Route::aliasMiddleware('df.cors', HandleCors::class);
        Route::prependMiddlewareToGroup('df.api', 'df.cors');
    }

    /**
     * Find the options for the current request, based on the paths/hosts settings.
     *
     * @param Request $request
     *
     * @return array
     * @throws \Exception
     */
    protected function getOptions(Request $request)
    {
        $configs = $this->getCorsConfigs();
        $uri = $request->getPathInfo() ?: '/';

        /** @var CorsConfig $bestMatch */
        $bestMatch = null;
        foreach ($configs as $config) {
            $path = $config->path;
            if ($request->is($path) || (Str::startsWith($path, '^') && preg_match('{' . $path . '}i', $uri))) {
                if ($bestMatch) {
                    // simple compare path lengths for accuracy
                    if (strlen($path) > strlen($bestMatch->path)) {
                        $bestMatch = $config;
                    }
                } else {
                    $bestMatch = $config;
                }
            }
        }

        if ($bestMatch) {
            return [
                "allowedOrigins"      => explode(',', $bestMatch->origin),
                "allowedOriginsPatterns" => [],
                "allowedHeaders"      => explode(',', $bestMatch->header),
                "exposedHeaders"      => explode(',', $bestMatch->exposed_header),
                "allowedMethods"      => $bestMatch->method,
                "maxAge"              => $bestMatch->max_age,
                "supportsCredentials" => $bestMatch->supports_credentials,
            ];
        }

        return [];
    }

    /**
     * @return CorsConfig[]
     * @throws \Exception
     */
    protected function getCorsConfigs()
    {
        try {
            $cors = Cache::remember(CorsConfig::CACHE_KEY, \Config::get('df.default_cache_ttl'), function (){
                return CorsConfig::whereEnabled(true)->get();
            });

            return $cors;
        } catch (\Exception $e) {
            if ($e instanceof QueryException || $e instanceof \PDOException) {
                if (isset($this->app, $this->app['log'])) {
                    \Log::alert('Could not get cors config from DB - ' . $e->getMessage());
                }

                return [];
            } else {
                throw $e;
            }
        }
    }
}
