## DreamFactory Core v0.5

[![Total Downloads](https://poser.pugx.org/dreamfactory/df-core/d/total.svg)](https://packagist.org/packages/dreamfactory/df-core)
[![Latest Stable Version](https://poser.pugx.org/dreamfactory/df-core/v/stable.svg)](https://packagist.org/packages/dreamfactory/df-core)
[![Latest Unstable Version](https://poser.pugx.org/dreamfactory/df-core/v/unstable.svg)](https://packagist.org/packages/dreamfactory/df-core)
[![License](https://poser.pugx.org/dreamfactory/df-core/license.svg)](http://www.apache.org/licenses/LICENSE-2.0)

> **Note:** This repository contains the core code of the DreamFactory platform. If you want the full DreamFactory platform, visit the main [DreamFactory repository](https://github.com/dreamfactorysoftware/dreamfactory).

## Overview

DreamFactory(™) Core is a package built on top of the Laravel framework, and as such retains the requirements of the [Laravel v5.2 framework](https://github.com/laravel/framework). 

## Documentation

Documentation for the platform can be found on the [DreamFactory wiki](http://wiki.dreamfactory.com).

## Installation

> **Note:** This document is currently intended for developers who desire to add DreamFactory to an existing Laravel project. 
It covers how to setup a local environment to start developing DreamFactory(™) packages. 
For more information, see the [full platform repository](https://github.com/dreamfactorysoftware/dreamfactory).


Edit your project’s composer.json to require the following package.

	“require”:{
		"dreamfactory/df-core": "~0.5.0"
	}

You may also need to add the following…

	"minimum-stability": "dev",
	"prefer-stable": true,


Save your composer.json and do a "composer update" to install the package.
Once the package is installed edit your config/app.php file to add the DfServiceProvider in the Providers array.

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

	phpunit vendor/dreamfactory/df-core/tests/

[Note: Remember to turn off laravel 5’s CSRF token validation or you need to supply the valid token for every api call. This can be turned off by commenting out the VerifyCsrfToken middleware inside app/Http/Kernel.php]

## Feedback and Contributions

* Feedback is welcome in the form of pull requests and/or issues.
* Contributions should generally follow the strategy outlined in ["Contributing to a project"](https://help.github.com/articles/fork-a-repo#contributing-to-a-project)
* All pull requests must be in a ["git flow"](https://github.com/nvie/gitflow) feature branch and formatted as [PSR-2 compliant](http://www.php-fig.org/psr/psr-2/) to be considered.

### License

The DreamFactory core is open-sourced software available for use under the [Apache Version 2.0 license](http://www.apache.org/licenses/LICENSE-2.0).
