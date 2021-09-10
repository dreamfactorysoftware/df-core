<?php

namespace DreamFactory\Core\Providers;

use Barryvdh\Cors\HandleCors;
use Barryvdh\Cors\HandlePreflight;
use Asm89\Stack\CorsService;
use DreamFactory\Core\Models\CorsConfig;
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
        $config = $this->getOptions($request);
        $this->app->singleton(CorsService::class, function () use ($config){

            if (isset($config['allowedOrigins'])) {
                foreach ($config['allowedOrigins'] as $origin) {
                    if (strpos($origin, '*') !== false) {
                        $config['allowedOriginsPatterns'][] = $this->convertWildcardToPattern($origin);
                    }
                }
            }
            return new CorsService($config);
        });

        /** @noinspection PhpUndefinedMethodInspection */
        //$this->app['router']->middleware('cors', HandleCors::class);

        if (method_exists(\Illuminate\Routing\Router::class, 'aliasMiddleware')) {
            Route::aliasMiddleware('df.cors', HandleCors::class);
        } else {
            /** @noinspection PhpUndefinedMethodInspection */
            Route::middleware('df.cors', HandleCors::class);
        }

        Route::prependMiddlewareToGroup('df.api', 'df.cors');

        if ($request->isMethod('OPTIONS')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $kernel->prependMiddleware(HandlePreflight::class);
        }
    }

    /**
     * Create a pattern for a wildcard, based on Str::is() from Laravel
     *
     * @see https://github.com/laravel/framework/blob/5.5/src/Illuminate/Support/Str.php
     * @param $pattern
     * @return string
     */
    protected function convertWildcardToPattern($pattern)
    {
        $pattern = preg_quote($pattern, '#');

        // Asterisks are translated into zero-or-more regular expression wildcards
        // to make it convenient to check if the strings starts with the given
        // pattern such as "library/*", making any string check convenient.
        $pattern = str_replace('\*', '.*', $pattern);

        return '#^'.$pattern.'\z#u';
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
