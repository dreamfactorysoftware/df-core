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

use DreamFactory\Rave\Handlers\Events\ServiceEventHandler;
use DreamFactory\Rave\Providers\BaseServiceProvider;

class RaveServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/df.php'  => config_path(),
                __DIR__ . '/../views/test_rest.html'   => public_path(),
                __DIR__ . '/../storage/' => storage_path(),
            ]
        );

        include __DIR__ . '/Http/RaveRoutes.php';

        $router = $this->app['router'];
        $router->middleware( 'access_check', 'DreamFactory\Rave\Http\Middleware\AccessCheck' );
    }

    public function register()
    {
        $subscriber = new ServiceEventHandler();
        \Event::subscribe($subscriber);
    }
}