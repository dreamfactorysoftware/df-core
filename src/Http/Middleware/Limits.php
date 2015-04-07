<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2015 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Dreamfactory\Rave\Http\Middleware;

use Closure;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\TooManyRequestsException;
use Illuminate\Contracts\Routing\Middleware;

class Limits
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle( $request, Closure $next )
    {
        // Get limits
        $limits = \Config::get( 'api_limits' );

        list( $userName, $userRole ) = $this->_getUserAndRole();

        $apiName = $this->_getApiKey();

        $serviceName = $this->_getServiceName();

        // Build the list of API Hits to check

        $apiKeysToCheck = array('api.default' => 0);

        if ( empty( $userRole ) === false )
        {
            $apiKeysToCheck['api.' . $userRole] = 0;
        }

        if ( empty( $userName ) === false )
        {
            $apiKeysToCheck['api.' . $userName] = 0;
        }

        if ( empty( $serviceName ) === false )
        {
            $apiKeysToCheck['api.' . $serviceName] = 0;

            if ( empty( $userRole ) === false )
            {
                $apiKeysToCheck['api.' . $serviceName . '.' . $userRole] = 0;
            }

            if ( empty( $userName ) === false )
            {
                $apiKeysToCheck['api.' . $serviceName . '.' . $userName] = 0;
            }
        }

        if ( empty( $apiName ) === false )
        {
            $apiKeysToCheck['api.' . $apiName] = 0;

            if ( empty( $userRole ) === false )
            {
                $apiKeysToCheck['api.' . $apiName . '.' . $userRole] = 0;
            }

            if ( empty( $userName ) === false )
            {
                $apiKeysToCheck['api.' . $apiName . "." . $userName] = 0;
            }

            if ( empty( $serviceName ) === false )
            {
                $apiKeysToCheck['api.' . $apiName . "." . $serviceName] = 0;

                if ( empty( $userRole ) === false )
                {
                    $apiKeysToCheck['api.' . $apiName . "." . $serviceName . '.' . $userRole] = 0;
                }

                if ( empty( $userName ) === false )
                {
                    $apiKeysToCheck['api.' . $apiName . "." . $serviceName . '.' . $userName] = 0;
                }
            }
        }

//        print_r($limits);
//        print_r($apiKeysToCheck);

        $overLimit = false;
        try
        {
            foreach ( $limits['api'] as $key => $limit )
            {
                if ( array_key_exists( $key, $apiKeysToCheck ) === true )
                {

                    $cacheValue = \Cache::get( $key, 0 );
                    $cacheValue++;
                    \Cache::put( $key, $cacheValue, $limit['period'] );
                    if ( $cacheValue > $limit['limit'] )
                    {
                        $overLimit = true;
                    }
                }

            }
        }
        catch ( Exception $e )
        {
            throw new InternalServerErrorException( 'Unable to update cache' );
        }

        if ($overLimit === true) {
            throw new TooManyRequestsException('Specified connection limit exceeded');
        }

        return $next( $request );
    }

    /*
     * Stub to get User and Role name from the authentication session/cookie
     */

    private function _getUserAndRole()
    {
        return ['userName', 'userRole'];
    }

    /*
     * Stub to get the API Key
     */

    private function _getApiKey()
    {
        return 'apiName';
    }

    /*
     * Stub to get the Service name
     */

    private function _getServiceName()
    {
        return 'serviceName';
    }

}
