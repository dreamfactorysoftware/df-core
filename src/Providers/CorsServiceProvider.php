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
        
        $config = $this->getOptions($request);
        $this->setLaravelCorsConfig($api_prefix, $config);

        $this->app->singleton(CorsService::class, function () use ($config){
            return new DfCorsService($config);
        });

        Route::aliasMiddleware('df.cors', HandleCors::class);
        Route::prependMiddlewareToGroup('df.api', 'df.cors');
    }

    /**
     * Keep Laravel's CORS middleware aligned with DreamFactory's DB-backed CORS config.
     *
     * Laravel's HandleCors middleware calls setOptions(config('cors')) on every
     * request. Without this, Laravel's framework defaults can replace the
     * DreamFactory CORS row selected above.
     *
     * @param string $apiPrefix
     * @param array  $options
     */
    protected function setLaravelCorsConfig(string $apiPrefix, array $options): void
    {
        $corsConfig = array_merge(config('cors', []), [
            'paths'                    => [$apiPrefix . '/*'],
            'allowedOrigins'           => $options['allowedOrigins'] ?? [],
            'allowedOriginsPatterns'   => $options['allowedOriginsPatterns'] ?? [],
            'allowedHeaders'           => $options['allowedHeaders'] ?? [],
            'allowedMethods'           => $options['allowedMethods'] ?? [],
            'exposedHeaders'           => $options['exposedHeaders'] ?? [],
            'maxAge'                   => $options['maxAge'] ?? 0,
            'supportsCredentials'      => $options['supportsCredentials'] ?? false,
            'allowed_origins'          => $options['allowedOrigins'] ?? [],
            'allowed_origins_patterns' => $options['allowedOriginsPatterns'] ?? [],
            'allowed_headers'          => $options['allowedHeaders'] ?? [],
            'allowed_methods'          => $options['allowedMethods'] ?? [],
            'exposed_headers'          => $options['exposedHeaders'] ?? [],
            'max_age'                  => $options['maxAge'] ?? 0,
            'supports_credentials'     => $options['supportsCredentials'] ?? false,
        ]);

        config(['cors' => $corsConfig]);
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
