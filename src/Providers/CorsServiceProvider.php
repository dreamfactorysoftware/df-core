<?php
namespace DreamFactory\Core\Providers;

use Barryvdh\Cors\HandleCors;
use Barryvdh\Cors\HandlePreflight;
use Barryvdh\Cors\Stack\CorsService;
use Illuminate\Database\QueryException;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Illuminate\Support\Str;

class CorsServiceProvider extends BaseServiceProvider
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
     * @param Request $request
     * @param Kernel $kernel
     */
    public function boot(Request $request, Kernel $kernel)
    {
        $config = $this->getOptions($request);
        $this->app->singleton(CorsService::class, function () use ($config){
            return new CorsService($config);
        });

        $this->app['router']->middleware('cors', HandleCors::class);

        if ($request->isMethod('OPTIONS')) {
            $kernel->prependMiddleware(HandlePreflight::class);
        }
    }

    /**
     * Find the options for the current request, based on the paths/hosts settings.
     *
     * @param Request $request
     *
     * @return array
     */
    protected function getOptions(Request $request)
    {
        $defaults = $this->app['config']->get('df.cors.defaults', []);
        $paths = $this->getPath();

        $uri = $request->getPathInfo() ?: '/';

        foreach ($paths as $pathPattern => $options) {
            //Check for legacy patterns
            if ($request->is($pathPattern) ||
                (Str::startsWith($pathPattern, '^') && preg_match('{' . $pathPattern . '}i', $uri))
            ) {
                $options = array_merge($defaults, $options);

                return $options;
            }
        }

        return $defaults;
    }

    /**
     * @return array
     * @throws \Exception
     */
    protected function getPath()
    {
        try {
            $cors = \DB::table('cors_config')->whereRaw('enabled = 1')->get();
        } catch (\Exception $e){
            if($e instanceof QueryException || $e instanceof \PDOException){
                isset($this->app,$this->app['log']) && \Log::alert('Could not get cors config from DB - '.$e->getMessage());

                return [];
            } else {
                throw $e;
            }
        }
        $path = [];

        if (!empty($cors)) {
            foreach ($cors as $cc) {
                $path[$cc->path] = [
                    "allowedOrigins" => explode(',', $cc->origin),
                    "allowedHeaders" => explode(',', $cc->header),
                    "allowedMethods" => $cc->method,
                    "maxAge"         => $cc->max_age
                ];
            }
        }

        return $path;
    }
}
