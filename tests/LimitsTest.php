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

    public function testDspOnly()
    {
        // Use the dsp only limit configuration

//        \Config::set( 'api_limits', $this->dspOnly );
//
//        $this->call( "GET", "api/apiName" );
//
//        $this->checkLimits($this->dspOnly['api']);
    }

    public function testDspOnlyOverLimit() {

        // Use the dsp only limit configuration

        \Config::set( 'api_limits', $this->dspOnly );

        $response = $this->call( "GET", "api/apiName" );

        // Make a second call so it's now over the limit
        $response = $this->call( "GET", "api/apiName" );

        print_r($response->getStatusCode());


    }

    private function checkLimits(array $limits) {
        foreach ( $limits as $key => $limit )
        {
            $this->assertEquals($limit['limit'], \Cache::get( $key, 0 ));
        }
    }
}
