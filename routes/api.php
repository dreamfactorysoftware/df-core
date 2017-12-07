<?php

$versionPattern = 'v[0-9.]+';
$servicePattern = '[_0-9a-zA-Z-.]+';
$resourcePathPattern = '[0-9a-zA-Z-_@&\#\!=,:;\/\^\$\.\|\{\}\[\]\(\)\*\+\? ]+';

Route::get('', 'RestController@index');
// Support old versioning in URL, i.e api/v2 and api/v2/service
Route::get('{version}', 'RestController@index')->where(['version' => $versionPattern]);
Route::any('{version}/{service}/{resource?}', 'RestController@handleVersionedService')->where(
    ['version' => $versionPattern, 'service' => $servicePattern, 'resource' => $resourcePathPattern]
);
Route::any('{service}/{resource?}', 'RestController@handleService')->where(
    ['service' => $servicePattern, 'resource' => $resourcePathPattern]
);
