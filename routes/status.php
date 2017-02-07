<?php

/*
|--------------------------------------------------------------------------
| System Status Routes
|--------------------------------------------------------------------------
|
| This route provides status output of the DreamFactory system.
|
*/

Route::get('/status', 'StatusController@index');
