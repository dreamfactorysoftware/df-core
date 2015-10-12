<?php
namespace DreamFactory\Core\Providers;

use Barryvdh\Cors\ServiceProvider;
use Asm89\Stack\CorsService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use DreamFactory\Core\Models\CorsConfig;

class DfCorsServiceProvider extends ServiceProvider
{
    /**
     * Add the Cors middleware to the router.
     * @param Request $request
     * @param Kernel $kernel
     */
    public function boot(Request $request, Kernel $kernel)
    {
        /** @var \Illuminate\Http\Request $request */
        $request = $this->app['request'];
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
        $host = $request->getHost();

        foreach ($paths as $pathPattern => $options) {
            //Check for legacy patterns
            if ($request->is($pathPattern) ||
                (Str::startsWith($pathPattern, '^') && preg_match('{' . $pathPattern . '}i', $uri))
            ) {
                $options = array_merge($defaults, $options);

                // skip if the host is not matching
                if (isset($options['hosts']) && count($options['hosts']) > 0) {
                    foreach ($options['hosts'] as $hostPattern) {
                        if (Str::is($hostPattern, $host)) {
                            return $options;
                        }
                    }
                    continue;
                }

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
                \Log::alert('Could not get cors config from DB - '.$e->getMessage());
                return [];
            } else {
                throw $e;
            }
        }
        $path = [];

        if (!empty($cors)) {
            $path = [];
            foreach ($cors as $p) {
                $cc = new CorsConfig([
                    'id'      => $p->id,
                    'path'    => $p->path,
                    'origin'  => $p->origin,
                    'header'  => $p->header,
                    'method'  => $p->method,
                    'max_age' => $p->max_age,
                    'enabled' => $p->enabled
                ]);
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