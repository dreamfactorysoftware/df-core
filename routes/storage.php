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

Route::get('{storage}/{path}', 'StorageController@handleGET')->where(
    ['storage' => $servicePattern, 'path' => $resourcePathPattern]
);
