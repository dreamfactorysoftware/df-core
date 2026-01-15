<?php

/*
|--------------------------------------------------------------------------
| REST API Routes
|--------------------------------------------------------------------------
|
| These routes provides RESTful interface to the DreamFactory platform.
|
*/

Route::prefix(config('df.api_route_prefix', 'api'))
    ->middleware('df.api')
    ->group(function () {
        $versionPattern = 'v[0-9.]+';
        $servicePattern = '[_0-9a-zA-Z-.]+';
        $resourcePathPattern = '[0-9a-zA-ZÀ-ÿ-_@&\#\!=,:;\/\^\$\.\|\{\}\[\]\(\)\*\+\?\' ]+';
        $controller = 'DreamFactory\Core\Http\Controllers\RestController';
        // Don't use any() below, or include OPTIONS here, breaks CORS
        $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

        Route::get('', $controller . '@index');
        // Support old versioning in URL, i.e api/v2 and api/v2/service
        Route::get('{version}', $controller . '@index')->where(['version' => $versionPattern]);
        Route::match($verbs, '{version}/{service}/{resource?}', $controller . '@handleVersionedService')->where(
            ['version' => $versionPattern, 'service' => $servicePattern, 'resource' => $resourcePathPattern]
        );
        Route::match($verbs, '{service}/{resource?}', $controller . '@handleService')->where(
            ['service' => $servicePattern, 'resource' => $resourcePathPattern]
        );
    }
);

/*
|--------------------------------------------------------------------------
| System Status Routes
|--------------------------------------------------------------------------
|
| This route provides status output of the DreamFactory system.
|
*/

Route::prefix(config('df.status_route_prefix', 'status'))
    ->middleware('df.cors')
    ->group(function () {
        Route::get('', 'DreamFactory\Core\Http\Controllers\StatusController@index');
    }
    );
