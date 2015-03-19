# DreamFactory RAVE(™)

This package contains the DreamFactory Rave(™). DreamFactory Rave(™) is a laravel 5 package that can be installed on any laravel 5 projects. 


## Installation

[Note: This document is currently intended for developers only at this time. It covers how to setup a dev (local) environment to start developing Rave(™) packages.]


Edit your project’s composer.json to require the following package.

	“require”:{
		"dreamfactory/rave-common": "dev-develop as dev-master"
	}

	Note: dreamfactory/rave-common is currently a private repo therefore you need to setup the repository for it in your composer.json file.

	"repositories":[
		{
		 "type": "vcs",
		 	"url":  "git@github.com:dreamfactorysoftware/rave-common.git"
		}
	]

	You may also need to add the following…

	"minimum-stability": "dev",
	"prefer-stable": true,


Save your composer.json and do a "composer update" to install the package.
Once the package is installed edit your config/app.php file to add the RaveServiceProvider in the Providers array.

	‘providers’ => [
		….,
		….,
		'DreamFactory\Rave\RaveServiceProvider'
	]

Next run "php artisan vendor:publish" to publish the config file rave.php to config/ directory and a helpful test_rest.html file to public/ directory.

dreamfactory/rave-common package also includes some helpful *-dist files inside the config directory. You can take a look at that and copy over what’s needed to the corresponding files of your app.
If you have setup your database connection right in your .env file then run the following migration.
	
	php artisan migrate --path=vendor/dreamfactory/rave-common/database/migrations/

After the migration run the following seeder class.

	php artisan db:seed --class=DreamFactory\\Rave\\Database\\Seeds\\DatabaseSeeder

Now if you have setup the phpunit config right in phpunit.xml (Use the supplied phpunit.xml-dist file in the package to use the right params) file then you should be able to run the unit tests.

	phpunit vendor/dreamfactory/rave-common/tests/

[Note: Remember to turn off laravel 5’s CSRF token validation or you need to supply the valid token for every api call. This can be turned off by commenting out the VerifyCsrfToken middleware inside app/Http/Kernel.php]
