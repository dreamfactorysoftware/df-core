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

namespace DreamFactory\Rave\Utility;

use DreamFactory\Rave\Enums\ContentTypes;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Models\Service;
use DreamFactory\Rave\Services\SystemManager;

/**
 * Class ServiceHandler
 *
 * @package DreamFactory\Rave\Utility
 */
class ServiceHandler
{
    /**
     * @param $apiName
     *
     * @return mixed
     * @throws NotFoundException
     */
    public static function getService( $apiName )
    {
        $apiName = strtolower( trim( $apiName ) );

        if ( 'system' == $apiName )
        {
            $settings = [
                'name'        => 'system',
                'label'       => 'System Manager',
                'description' => 'Handles all system resources and configuration'
            ];

            return new SystemManager( $settings );
        }

        $service = Service::whereName( $apiName )->get()->first();
        if ( $service instanceof Service )
        {
            $serviceClass = $service->serviceType()->first()->class_name;
            $settings = $service->toArray();

            return new $serviceClass( $settings );
        }
        else
        {
            $msg = "Could not find a service for " . $apiName;
            throw new NotFoundException( $msg );
        }
    }

    /**
     * @param null|string $version
     * @param      $service
     * @param null $resource
     * @param int  $outputFormat
     *
     * @return mixed
     * @throws NotFoundException
     */
    public static function processRequest( $version, $service, $resource = null, $outputFormat = ContentTypes::JSON )
    {
        $request = new ServiceRequest();
        $request->setApiVersion($version);

        return self::getService( $service )->handleRequest( $request, $resource, $outputFormat );
    }

    /**
     * @return array
     */
    public static function listServices()
    {
        $system = [[
            'name'        => 'system',
            'label'       => 'System Manager'
        ]];

        $services = array_merge( $system, Service::available());

        return array( 'service' => $services );

    }
}