# DreamFactory Core

This package contains the DreamFactory Core, a Laravel 5 package that can be installed on any Laravel 5 project. 


## Installation

[Note: This document is currently intended for developers only at this time. It covers how to setup a dev (local) environment to start developing DreamFactory(™) packages.]


Edit your project’s composer.json to require the following package.

	“require”:{
		"dreamfactory/df-core": "dev-develop as dev-master"
	}

	You may also need to add the following…

	"minimum-stability": "dev",
	"prefer-stable": true,


Save your composer.json and do a "composer update" to install the package.
Once the package is installed edit your config/app.php file to add the RaveServiceProvider in the Providers array.

	‘providers’ => [
		….,
		….,
		'DreamFactory\Core\DfServiceProvider'
	]

Next run "php artisan vendor:publish" to publish the config file df.php to config/ directory and a helpful test_rest.html file to public/ directory.

dreamfactory/df-core package also includes some helpful *-dist files inside the config directory. You can take a look at that and copy over what’s needed to the corresponding files of your app.
If you have setup your database connection right in your .env file then run the following migration.
	
	php artisan migrate --path=vendor/dreamfactory/df-core/database/migrations/

After the migration run the following seeder class.

	php artisan db:seed --class=DreamFactory\\Core\\Database\\Seeds\\DatabaseSeeder

Now if you have setup the phpunit config right in phpunit.xml (Use the supplied phpunit.xml-dist file in the package to use the right params) file then you should be able to run the unit tests.

	phpunit vendor/dreamfactory/df-common/tests/

[Note: Remember to turn off Laravel 5’s CSRF token validation or you need to supply the valid token for every api call. This can be turned off by commenting out the VerifyCsrfToken middleware inside app/Http/Kernel.php]
