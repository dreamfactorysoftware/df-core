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

//Todo:Any better way to do this?
//Treat merge as patch
$method = Request::getMethod();
if ( \DreamFactory\Library\Utility\Enums\Verbs::MERGE === strtoupper( $method ) )
{
    Request::setMethod( \DreamFactory\Library\Utility\Enums\Verbs::PATCH );
}

$resourcePathPattern = '[0-9a-zA-Z-_@&\#\!=,:;\/\^\$\.\|\{\}\[\]\(\)\*\+\? ]+';
$servicePattern = '[_0-9a-zA-Z-]+';

Route::group(
    [ 'namespace' => 'DreamFactory\Rave\Http\Controllers' ],
    function () use ( $resourcePathPattern, $servicePattern )
    {
        Route::get('rave', 'SplashController@index');

        Route::get('rave/launchpad', 'LaunchpadController@index');

        Route::get('rave/admin', 'AdminController@index');

        Route::controllers([
                               'rave/auth' => 'Auth\AuthController',
                               'rave/password' => 'Auth\PasswordController',
                           ]);


        Route::group(
            [ 'prefix' => 'api' ],
            function () use ( $resourcePathPattern, $servicePattern )
            {
                Route::get( '{version}/', 'RestController@index' );
                Route::get( '{version}/{service}/{resource?}', 'RestController@handleGET' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::post( '{version}/{service}/{resource?}', 'RestController@handlePOST' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::put( '{version}/{service}/{resource?}', 'RestController@handlePUT' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::patch( '{version}/{service}/{resource?}', 'RestController@handlePATCH' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::delete( '{version}/{service}/{resource?}', 'RestController@handleDELETE' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
            }
        );

        Route::group(
            [ 'prefix' => 'rest' ],
            function () use ( $resourcePathPattern, $servicePattern )
            {
                Route::get( '/', 'RestController@index' );
                Route::get( '{service}/{resource?}', 'RestController@handleV1GET' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::post( '{service}/{resource?}', 'RestController@handleV1POST' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::put( '{service}/{resource?}', 'RestController@handleV1PUT' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::patch( '{service}/{resource?}', 'RestController@handleV1PATCH' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
                Route::delete( '{service}/{resource?}', 'RestController@handleV1DELETE' )->where(
                    [ 'service' => $servicePattern, 'resource' => $resourcePathPattern ]
                );
            }
        );
    }
);