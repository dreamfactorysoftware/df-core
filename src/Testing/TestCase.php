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
namespace DreamFactory\Rave\Testing;

use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Artisan;
use DB;

class TestCase extends LaravelTestCase
{
    /**
     * URL prefix rest/ api, api/v1, api/v2 etc.
     *
     * @var string
     */
    protected $prefix = 'rest';

    /**
     * A flag to make sure that the stage() method gets to run one time only.
     *
     * @var bool
     */
    protected static $staged = false;

    /**
     * Runs before every test class.
     */
    public static function setupBeforeClass()
    {
        echo "\n------------------------------------------------------------------------------\n";
        echo "Running test: " . get_called_class() . "\n";
        echo "------------------------------------------------------------------------------\n\n";
    }

    /**
     * Runs before every test.
     */
    public function setUp()
    {
        parent::setUp();

        if ( false === static::$staged )
        {
            $this->stage();
            static::$staged = true;
        }
    }

    /**
     * This method is used for staging the overall
     * test environment. Which usually covers things like
     * running database migrations and seeders.
     *
     * In order to override and run this method on a child
     * class, you must set the static::$staged property to
     * false in the respective child class.
     */
    public function stage()
    {
        Artisan::call( 'migrate', ['--path' => 'vendor/dreamfactory/rave/database/migrations/'] );
        Artisan::call( 'db:seed', ['--class' => 'DreamFactory\\Rave\\Database\\Seeds\\DatabaseSeeder'] );
    }

    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__ . '/../../../../../bootstrap/app.php';

        $app->make( 'Illuminate\Contracts\Console\Kernel' )->bootstrap();

        return $app;
    }

    /**
     * @param $verb
     * @param $url
     * @param $payload
     *
     * @return \Illuminate\Http\Response
     */
    protected function callWithPayload( $verb, $url, $payload )
    {
        $rs = $this->call( $verb, $url, [ ], [ ], [ ], [ ], $payload );

        return $rs;
    }

    /**
     * Checks to see if a service already exists
     *
     * @param string $serviceName
     *
     * @return bool
     */
    protected function serviceExists( $serviceName )
    {
        return DB::table( 'service' )->where( 'name', $serviceName )->exists();
    }
}
