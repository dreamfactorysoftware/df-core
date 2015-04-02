<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave;


use Barryvdh\Cors\CorsServiceProvider;
use Asm89\Stack\CorsService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use DreamFactory\Rave\Models\CorsConfig;

class RaveCorsServiceProvider extends CorsServiceProvider
{
    /**
     * Register the service provider.
     *
     * Overriding this method only to use right cors.php config file
     * from the rave package.
     *
     * @return void
     */
    public function register()
    {
        /** @var \Illuminate\Http\Request $request */
        $request = $this->app['request'];

        // Register the config publish path
        $configPath = __DIR__ . '/../config/cors.php';
        $this->publishes([$configPath => config_path('cors.php')]);

        $this->app->bind('Asm89\Stack\CorsService', function() use($request){
            return new CorsService($this->getOptions($request));
        });

    }

    /**
     * Find the options for the current request, based on the paths/hosts settings.
     *
     * @param Request $request
     * @return array
     */
    protected function getOptions(Request $request)
    {
        $defaults = $this->app['config']->get('cors.defaults', []);
        $paths = $this->getPath();

        $uri = $request->getPathInfo() ? : '/';
        $host = $request->getHost();

        foreach ($paths as $pathPattern => $options) {
            //Check for legacy patterns
            if ($request->is($pathPattern) || (Str::startsWith($pathPattern, '^') && preg_match('{' . $pathPattern . '}i', $uri))) {
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

        if(!empty($cors))
        {
            $path = [ ];
            foreach ( $cors as $p )
            {
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