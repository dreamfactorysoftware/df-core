<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Rave\Components;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Enums\ApiDocFormatTypes;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Models\Service;
use DreamFactory\Rave\Services\BaseRestService;
use DreamFactory\Rave\Utility\CacheUtilities;

/**
 * ApiDocManager
 * DreamFactory API documentation manager
 *
 */
class ApiDocManager
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string A cached process-handling events list derived from API Docs
     */
    const PROCESS_EVENT_CACHE_KEY = 'process_events';
    /**
     * @const string A cached broadcast events list derived from API Docs
     */
    const BROADCAST_EVENT_CACHE_KEY = 'broadcast_events';
    /**
     * @const integer How long a swagger cache will live, 1440 = 24 minutes (default session timeout).
     */
    const CACHE_TTL = 1440;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array The process-handling event map
     */
    protected static $processEventMap = false;
    /**
     * @var array The broadcast event map
     */
    protected static $broadcastEventMap = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Internal building method builds all static services and some dynamic
     * services from file annotations, otherwise swagger info is loaded from
     * database or storage files for each service, if it exists.
     *
     * @throws \Exception
     */
    protected static function buildEventMaps()
    {
        \Log::info( 'Building event cache' );

        //  Build event mapping from services in database

        //	Initialize the event map
        static::$processEventMap = static::$processEventMap ?: [ ];
        static::$broadcastEventMap = static::$broadcastEventMap ?: [ ];

        //  Pull any custom swagger docs
        $_result = Service::with(
            [
                'serviceDocs' => function ( $query )
                {
                    $query->where( 'format', ApiDocFormatTypes::SWAGGER );

                }
            ]
        )->get();

        //	Spin through services and pull the configs
        $_services = [ ];
        foreach ( $_result as $_service )
        {
            $_apiName = $_service->name;
            $_content = static::getStoredContentForService( $_service );

            if ( empty( $_content ) )
            {
                \Log::info( '  * No Swagger content found for service "' . $_apiName . '"' );
                continue;
            }

            if ( !isset( static::$processEventMap[$_apiName] ) ||
                 !is_array( static::$processEventMap[$_apiName] ) ||
                 empty( static::$processEventMap[$_apiName] )
            )
            {
                static::$processEventMap[$_apiName] = [ ];
            }

            if ( !isset( static::$broadcastEventMap[$_apiName] ) ||
                 !is_array( static::$broadcastEventMap[$_apiName] ) ||
                 empty( static::$broadcastEventMap[$_apiName] )
            )
            {
                static::$broadcastEventMap[$_apiName] = [ ];
            }

            $_serviceEvents = static::_parseSwaggerEvents( $_apiName, $_content );

            // build main services list
            $_services[] = [
                'path'        => '/' . $_apiName,
                'description' => $_service->description
            ];

            //	Parse the events while we get the chance...
            static::$processEventMap[$_apiName] = array_merge(
                ArrayUtils::clean( static::$processEventMap[$_apiName] ),
                $_serviceEvents['script']
            );
            static::$broadcastEventMap[$_apiName] = array_merge(
                ArrayUtils::clean( static::$broadcastEventMap[$_apiName] ),
                $_serviceEvents['subscribe']
            );

            unset( $_content, $_filePath, $_service, $_serviceEvents );
        }

        //	Write event cache file
        if ( false === CacheUtilities::put(
                static::PROCESS_EVENT_CACHE_KEY,
                static::$processEventMap,
                static::CACHE_TTL
            )
        )
        {
            \Log::error( '  * System error creating swagger script-able event cache file: ' . static::PROCESS_EVENT_CACHE_KEY );
        }

        if ( false === CacheUtilities::put(
                static::BROADCAST_EVENT_CACHE_KEY,
                static::$broadcastEventMap,
                static::CACHE_TTL
            )
        )
        {
            \Log::error( '  * System error creating swagger subscribe-able event cache file: ' . static::BROADCAST_EVENT_CACHE_KEY );
        }

        \Log::info( 'Event cache build process complete' );

//        Platform::trigger( static::CACHE_REBUILT );
    }

    public static function getStoredContentForServiceByName( $name )
    {
        if ( !is_string( $name ) )
        {
            throw new BadRequestException( "Could not find a service for $name" );
        }

        $service = Service::whereName( $name )->get()->first();
        if ( empty( $service ) )
        {
            throw new NotFoundException( "Could not find a service for $name" );
        }

        return static::getStoredContentForService( $service );
    }

    public static function getStoredContentForService( Service $service )
    {
        // check the database records for custom doc in swagger, raml, etc.
        $info = $service->serviceDocs()->first();
        $content = ( isset( $info ) ) ? $info->content : null;
        if ( is_string( $content ) )
        {
            $content = json_decode( $content, true );
        }
        else
        {
            $serviceClass = $service->serviceType()->first()->class_name;
            $settings = $service->toArray();

            /** @var BaseRestService $obj */
            $obj = new $serviceClass( $settings );
            $content = $obj->getApiDocInfo();
        }

        return $content;
    }

    /**
     * @param string $apiName
     * @param array  $data
     *
     * @return array
     */
    protected static function _parseSwaggerEvents( $apiName, &$data )
    {
        $processEvents = [ ];
        $broadcastEvents = [ ];
        $eventCount = 0;

        foreach ( ArrayUtils::get( $data, 'apis', [ ] ) as $_ixApi => $api )
        {
            $apiProcessEvents = $apiBroadcastEvents = [ ];

            if ( null === ( $path = ArrayUtils::get( $api, 'path' ) ) )
            {
                \Log::notice( '  * Missing "path" in Swagger definition: ' . $apiName );
                continue;
            }

            $path = str_replace(
                [ '{api_name}', '/' ],
                [ $apiName, '.' ],
                trim( $path, '/' )
            );

            foreach ( ArrayUtils::get( $api, 'operations', [ ] ) as $_ixOps => $_operation )
            {
                if ( null !== ( $_eventNames = ArrayUtils::get( $_operation, 'event_name' ) ) )
                {
                    $_method = strtolower( ArrayUtils::get( $_operation, 'method', Verbs::GET ) );
                    $_eventsThrown = [ ];

                    if ( is_string( $_eventNames ) && false !== strpos( $_eventNames, ',' ) )
                    {
                        $_eventNames = explode( ',', $_eventNames );

                        //  Clean up any spaces...
                        foreach ( $_eventNames as &$_tempEvent )
                        {
                            $_tempEvent = trim( $_tempEvent );
                        }
                    }

                    if ( empty( $_eventNames ) )
                    {
                        $_eventNames = [ ];
                    }
                    else if ( !is_array( $_eventNames ) )
                    {
                        $_eventNames = [ $_eventNames ];
                    }

                    //  Set into master record
                    $data['apis'][$_ixApi]['operations'][$_ixOps]['event_name'] = $_eventNames;

                    foreach ( $_eventNames as $_ixEventNames => $_templateEventName )
                    {
                        $_eventName = str_replace(
                            [
                                '{api_name}',
                                $apiName . '.' . $apiName . '.',
                                '{action}',
                                '{request.method}'
                            ],
                            [
                                $apiName,
                                'system.' . $apiName . '.',
                                $_method,
                                $_method,
                            ],
                            $_templateEventName
                        );

                        $_eventsThrown[] = $_eventName;

                        //  Set actual name in swagger file
                        $data['apis'][$_ixApi]['operations'][$_ixOps]['event_name'][$_ixEventNames] = $_eventName;

                        $eventCount++;
                    }

                    $apiBroadcastEvents[$_method] = $_eventsThrown;
                    $apiProcessEvents[$_method] = [ "$path.$_method.pre_process", "$path.$_method.post_process" ];
                }

                unset( $_operation, $_eventsThrown );
            }

            $processEvents[str_ireplace( '{api_name}', $apiName, $path )] = $apiProcessEvents;
            $broadcastEvents[str_ireplace( '{api_name}', $apiName, $path )] = $apiBroadcastEvents;

            unset( $apiProcessEvents, $apiBroadcastEvents, $api );
        }

        \Log::debug( '  * Discovered ' . $eventCount . ' event(s).' );

        return [ 'script' => $processEvents, 'subscribe' => $broadcastEvents ];
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @return array
     */
    public static function getProcessEventMap()
    {
        if ( !empty( static::$processEventMap ) )
        {
            return static::$processEventMap;
        }

        static::$processEventMap = CacheUtilities::get( static::PROCESS_EVENT_CACHE_KEY );

        //	If we still have no event map, build it.
        if ( empty( static::$processEventMap ) )
        {
            static::buildEventMaps();
        }

        return static::$processEventMap;
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @return array
     */
    public static function getBroadcastEventMap()
    {
        if ( !empty( static::$broadcastEventMap ) )
        {
            return static::$broadcastEventMap;
        }

        static::$broadcastEventMap = CacheUtilities::get( static::BROADCAST_EVENT_CACHE_KEY );

        //	If we still have no event map, build it.
        if ( empty( static::$broadcastEventMap ) )
        {
            static::buildEventMaps();
        }

        return static::$broadcastEventMap;
    }

    /**
     * Clears the cache produced by the swagger annotations
     */
    public static function clearCache()
    {
        CacheUtilities::forget( static::PROCESS_EVENT_CACHE_KEY );
        CacheUtilities::forget( static::BROADCAST_EVENT_CACHE_KEY );

        // rebuild swagger cache
        static::buildEventMaps();
    }
}
