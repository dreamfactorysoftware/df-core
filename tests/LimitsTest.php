<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2015 DreamFactory Software, Inc. <support@dreamfactory.com>
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
/*
 * Limits test
 */

use Illuminate\Support\Facades\Config;

class LimitsTest extends \DreamFactory\Rave\Testing\TestCase
{

    // Configurations for various possible combos

    protected $dspOnly = [
        /* API Hits limits */
        'api' => [
            'api.default' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspRoleOnly = [

        /* API Hits limits */
        'api' => [
            'api.default'  => ['limit' => 1, 'period' => 1],
            'api.roleName' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspUserOnly = [

        /* API Hits limits */
        'api' => [
            'api.default'  => ['limit' => 1, 'period' => 1],
            'api.roleName' => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'api.userName' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiOnly = [

        /* API Hits limits */
        'api' => [
            'api.default'  => ['limit' => 1, 'period' => 1],
            'api.roleName' => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'api.userName' => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'api.apiName'  => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiRole = [

        /* API Hits limits */
        'api' => [
            'api.default'          => ['limit' => 1, 'period' => 1],
            'api.roleName'         => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'api.userName'         => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'api.apiName'          => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'api.apiName.roleName' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiUser = [

        /* API Hits limits */
        'api' => [
            'api.default'          => ['limit' => 1, 'period' => 1],
            'api.roleName'         => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'api.userName'         => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'api.apiName'          => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'api.apiName.userName' => ['limit' => 1, 'period' => 1]
        ]
    ];

    protected $dspApiService = [

        /* API Hits limits */
        'api' => [
            'api.default'             => ['limit' => 1, 'period' => 1],
            'api.roleName'            => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'api.userName'            => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'api.apiName'             => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'api.apiName.serviceName' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiServiceRole = [

        /* API Hits limits */
        'api' => [
            'api.default'                      => ['limit' => 1, 'period' => 1],
            'api.roleName'                     => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'api.userName'                     => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'api.apiName'                      => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'api.apiName.serviceName'          => ['limit' => 1, 'period' => 1], /* replace serviceName with the actual service name */
            'api.apiName.serviceName.roleName' => ['limit' => 1, 'period' => 1]
        ]

    ];

    protected $dspApiServiceUser = [

        /* API Hits limits */
        'api' => [
            'api.default'                      => ['limit' => 1, 'period' => 1],
            'api.roleName'                     => ['limit' => 1, 'period' => 1], /* replace roleName with the actual role name */
            'api.userName'                     => ['limit' => 1, 'period' => 1], /* replace userName with the actual user name */
            'api.apiName'                      => ['limit' => 1, 'period' => 1], /* replace apiName with the actual API name */
            'api.apiName.serviceName'          => ['limit' => 1, 'period' => 1], /* replace serviceName with the actual service name */
            'api.apiName.serviceName.userName' => ['limit' => 1, 'period' => 1]
        ]

    ];

    public function testLimitSet()
    {
        $limits =
            array(
                $this->dspOnly,
                $this->dspRoleOnly,
                $this->dspUserOnly,
                $this->dspApiOnly,
                $this->dspApiRole,
                $this->dspApiUser,
                $this->dspApiService,
                $this->dspApiServiceRole,
                $this->dspApiServiceUser
            );

        $this->_setTestMode();

        foreach ( $limits as $key => $limit )
        {
            $this->_setLimits( $limit );

            $this->call( "GET", "api/apiName" );

            $this->_checkLimits( $limit['api'] );



        }

        $this->_unsetTestMode();

    }

    public function testOverLimit()
    {
        $limits =
            array(
                $this->dspOnly,
                $this->dspRoleOnly,
                $this->dspUserOnly,
                $this->dspApiOnly,
                $this->dspApiRole,
                $this->dspApiUser,
                $this->dspApiService,
                $this->dspApiServiceRole,
                $this->dspApiServiceUser
            );

        $this->_setTestMode();

        foreach ( $limits as $limit )
        {
            $this->_setLimits($limit);

            $this->_checkOverLimit('api/apiName');

            foreach ($limit as $key => $value) {
                $this->_clearCache( $key );
            }

        }

        $this->_unsetTestMode();

    }

    public function testNoLimits() {
        // Test that everything works when there are no limits set

        $this->call( "GET", "api/apiName" );
        $this->call( "GET", "api/apiName" );
        $this->call( "GET", "api/apiName" );
        $response = $this->call( "GET", "api/apiName" );

        $this->assertEquals( 200, $response->getStatusCode() );
    }

    private function _checkLimits( array $limits )
    {
        foreach ( $limits as $key => $limit )
        {
            $this->assertEquals( $limit['limit'], \Cache::get( $key, 0 ) );

            $this->_clearCache( $key );

        }
    }

    private function _setLimits( array $limits )
    {
        \Config::set( 'api_limits', $limits );
    }

    private function _checkOverLimit( $path )
    {
        $response = $this->call( "GET", $path );

        // Make a second call so it's now over the limit
        $response = $this->call( "GET", $path );

        $this->assertEquals( 429, $response->getStatusCode() );
    }

    private function _clearCache($key) {
        // Make sure we're clean for the next iteration

        \Cache::forget( $key );
    }

    private function _setTestMode()
    {
        \Config::set( 'api_limits_test', true );
    }

    private function _unsetTestMode()
    {
        \Config::set( 'api_limits_test', false );
    }
}
