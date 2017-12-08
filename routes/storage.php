<?php

/*
|--------------------------------------------------------------------------
| Storage Services Routes
|--------------------------------------------------------------------------
|
| Here is where you can register routes for your DreamFactory file storage
| services. These routes give URL access to folders declared public in your
| file service's configuration.
|
*/

$resourcePathPattern = '[0-9a-zA-Z-_@&\#\!=,:;\/\^\$\.\|\{\}\[\]\(\)\*\+\? ]+';
$servicePattern = '[_0-9a-zA-Z-.]+';

if (empty(config('df.storage_route_prefix'))) {
    // without a route prefix, this needs to use valid service names, otherwise prohibits any additional routes
    $service = Request::segment(1);
    $storageServices = DreamFactory\Core\Facades\ServiceManager::getServiceNamesByGroup(DreamFactory\Core\Enums\ServiceTypeGroups::FILE, true);
    if (false !== array_search($service, $storageServices)) {
        Route::get('{storage}/{path}', 'StorageController@handleGET')->where(
            ['storage' => $servicePattern, 'path' => $resourcePathPattern]
        );
    }
} else {
    Route::get('{storage}/{path}', 'StorageController@handleGET')->where(
        ['storage' => $servicePattern, 'path' => $resourcePathPattern]
    );
}
