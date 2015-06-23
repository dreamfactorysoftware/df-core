<?php
namespace DreamFactory\Core;

use Barryvdh\Cors\CorsServiceProvider;
use Asm89\Stack\CorsService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use DreamFactory\Core\Models\CorsConfig;

class DfCorsServiceProvider extends CorsServiceProvider
{
    /**
     * Register the service provider.
     *
     * Overriding this method only to NOT use the cors.php config file
     * from the vendor's package. Instead using df.php config (cors.defaults).
     *
     * @return void
     */
    public function register()
    {
        /** @var \Illuminate\Http\Request $request */
        $request = $this->app['request'];

        $this->app->bind('Asm89\Stack\CorsService', function () use ($request){
            return new CorsService($this->getOptions($request));
        });
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
     * Gets the CORS settings from database table.
     *
     * @return array
     */
    protected function getPath()
    {
        $cors = CorsConfig::all()->toArray();
        $path = [];

        if (!empty($cors)) {
            $path = [];
            foreach ($cors as $p) {
                $path[$p['path']] = [
                    "allowedOrigins" => explode(',', $p['origin']),
                    "allowedHeaders" => explode(',', $p['header']),
                    "allowedMethods" => $p['method'],
                    "maxAge"         => $p['max_age']
                ];
            }
        }

        return $path;
    }
}