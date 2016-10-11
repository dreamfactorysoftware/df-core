<?php
namespace DreamFactory\Core\Providers;

use Asm89\Stack\CorsService;
use Barryvdh\Cors\ServiceProvider;
use DreamFactory\Core\Models\CorsConfig;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

class CorsServiceProvider extends ServiceProvider
{
    /**
     * Add the Cors middleware to the router.
     * @param Request $request
     * @param Kernel $kernel
     */
    public function boot(Request $request, Kernel $kernel)
    {
        $config = $this->getOptions($request);
        $this->app->bind('Asm89\Stack\CorsService', function () use ($config){
            return new CorsService($config);
        });

        $this->app['router']->middleware('cors', 'Barryvdh\Cors\HandleCors');

        if ($request->isMethod('OPTIONS')) {
            $kernel->pushMiddleware('Barryvdh\Cors\HandlePreflight');
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
            $path = [];
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
