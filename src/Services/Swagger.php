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
namespace DreamFactory\Rave\Services;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Enums\ApiDocFormatTypes;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Models\Service;

/**
 * Swagger
 * DSP API Documentation manager
 *
 */
class Swagger extends BaseRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string The current API version
     */
    const API_VERSION = '2.0';
    /**
     * @const string The Swagger version
     */
    const SWAGGER_VERSION = '1.2';
    /**
     * @const string The private cache file
     */
    const SWAGGER_CACHE_FILE = '_.json';
    /**
     * @const string A cached script-able events list derived from Swagger
     */
    const SWAGGER_SCRIPT_EVENT_CACHE_FILE = '_script_events.json';
    /**
     * @const string A cached subscribe-able events list derived from Swagger
     */
    const SWAGGER_SUBSCRIBE_EVENT_CACHE_FILE = '_subscribe_events.json';
    /**
     * @const integer How long a swagger cache will live, 1440 = 24 minutes (default session timeout).
     */
    const SWAGGER_CACHE_TTL = 1440;
    /**
     * @var string Triggered immediately after the swagger cache is cleared
     */
    const CACHE_CLEARED = 'swagger.cache_cleared';
    /**
     * @var string Triggered immediately after the swagger cache has been rebuilt
     */
    const CACHE_REBUILT = 'swagger.cache_rebuilt';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array The script-able event map
     */
    protected static $scriptEventMap = false;
    /**
     * @var array The subscribe-able event map
     */
    protected static $subscribeEventMap = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return array|string|bool
     */
    protected function handleGET()
    {
        // lock down access to valid apps only, can't check session permissions
        // here due to sdk access
//        Session::checkAppPermission( null, false );
        if ( $this->request->queryBool( 'refresh' ) )
        {
            static::clearCache();
        }

        if ( empty( $this->resource ) )
        {
            return static::getSwagger();
        }

        return static::getSwaggerForService( $this->resource );
    }

    /**
     * Internal building method builds all static services and some dynamic
     * services from file annotations, otherwise swagger info is loaded from
     * database or storage files for each service, if it exists.
     *
     * @return array
     * @throws \Exception
     */
    protected static function buildSwagger()
    {
        \Log::info( 'Building Swagger cache' );

        $_baseSwagger = [
            'swaggerVersion' => static::SWAGGER_VERSION,
            'apiVersion'     => \Config::get( 'rave.api_version', static::API_VERSION ),
            'basePath'       => url( '/rest' ),
        ];

        //  Build services from database
        //  Pull any custom swagger docs
        $_result = Service::with(
            [
                'serviceDocs' => function ( $query )
                {
                    $query->where( 'format', ApiDocFormatTypes::SWAGGER );

                }
            ]
        )->get();

        // gather the services
        $_services = [ ];

        //	Initialize the event map
        static::$scriptEventMap = static::$scriptEventMap ?: [ ];
        static::$subscribeEventMap = static::$subscribeEventMap ?: [ ];

        //	Spin through services and pull the configs
        foreach ( $_result as $_service )
        {
            $_apiName = $_service->name;
            $_content = static::getStoredContentForService( $_service );

            if ( empty( $_content ) )
            {
                \Log::info( '  * No Swagger content found for service "' . $_apiName . '"' );
                continue;
            }

            if ( !isset( static::$scriptEventMap[$_apiName] ) || !is_array( static::$scriptEventMap[$_apiName] ) || empty( static::$scriptEventMap[$_apiName] )
            )
            {
                static::$scriptEventMap[$_apiName] = [ ];
            }

            if ( !isset( static::$subscribeEventMap[$_apiName] ) ||
                 !is_array( static::$subscribeEventMap[$_apiName] ) ||
                 empty( static::$subscribeEventMap[$_apiName] )
            )
            {
                static::$subscribeEventMap[$_apiName] = [ ];
            }

            $_serviceEvents = static::_parseSwaggerEvents( $_apiName, $_content );

            $_content = array_merge( $_baseSwagger, $_content );
            $_content = json_encode( $_content, JSON_UNESCAPED_SLASHES );

            // replace service type placeholder with api name for this service instance
            $_content = str_replace( '/{api_name}', '/' . $_apiName, $_content );

            // cache it for later access
            if ( false === \Cache::add( $_apiName . '.json', $_content, static::SWAGGER_CACHE_TTL ) )
            {
                \Log::error( '  * System error creating swagger cache file: ' . $_apiName . '.json' );
                continue;
            }

            // build main services list
            $_services[] = [
                'path'        => '/' . $_apiName,
                'description' => $_service->description
            ];

            //	Parse the events while we get the chance...
            static::$scriptEventMap[$_apiName] = array_merge(
                ArrayUtils::clean( static::$scriptEventMap[$_apiName] ),
                $_serviceEvents['script']
            );
            static::$subscribeEventMap[$_apiName] = array_merge(
                ArrayUtils::clean( static::$subscribeEventMap[$_apiName] ),
                $_serviceEvents['subscribe']
            );

            unset( $_content, $_filePath, $_service, $_serviceEvents );
        }

        // cache main api listing file
        $_description = <<<HTML
HTML;

        $_resourceListing = [
            'swaggerVersion' => static::SWAGGER_VERSION,
            'apiVersion'     => static::API_VERSION,
            'authorizations' => [ 'apiKey' => [ 'type' => 'apiKey', 'passAs' => 'header' ] ],
            'info'           => [
                'title'       => 'DreamFactory Live API Documentation',
                'description' => $_description,
                //'termsOfServiceUrl' => 'http://www.dreamfactory.com/terms/',
                'contact'     => 'support@dreamfactory.com',
                'license'     => 'Apache 2.0',
                'licenseUrl'  => 'http://www.apache.org/licenses/LICENSE-2.0.html'
            ],
            /**
             * The events thrown that are relevant to Swagger
             */
            'events'         => [
                static::CACHE_CLEARED,
                static::CACHE_REBUILT,
            ],
        ];
        $_out = array_merge( $_resourceListing, [ 'apis' => $_services ] );

        if ( false === \Cache::add(
                static::SWAGGER_CACHE_FILE,
                json_encode( $_out, JSON_UNESCAPED_SLASHES ),
                static::SWAGGER_CACHE_TTL
            )
        )
        {
            \Log::error( '  * System error creating swagger cache file: ' . static::SWAGGER_CACHE_FILE );
        }

        //	Write event cache file
        if ( false === \Cache::add(
                static::SWAGGER_SCRIPT_EVENT_CACHE_FILE,
                json_encode( static::$scriptEventMap, JSON_UNESCAPED_SLASHES ),
                static::SWAGGER_CACHE_TTL
            )
        )
        {
            \Log::error( '  * System error creating swagger script-able event cache file: ' . static::SWAGGER_SCRIPT_EVENT_CACHE_FILE );
        }

        if ( false === \Cache::add(
                static::SWAGGER_SUBSCRIBE_EVENT_CACHE_FILE,
                json_encode( static::$subscribeEventMap, JSON_UNESCAPED_SLASHES ),
                static::SWAGGER_CACHE_TTL
            )
        )
        {
            \Log::error( '  * System error creating swagger subscribe-able event cache file: ' . static::SWAGGER_SUBSCRIBE_EVENT_CACHE_FILE );
        }

        \Log::info( 'Swagger cache build process complete' );

//        Platform::trigger( static::CACHE_REBUILT );

        return $_out;
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
        $scriptEvents = [ ];
        $subscribeEvents = [ ];
        $eventCount = 0;

        foreach ( ArrayUtils::get( $data, 'apis', [ ] ) as $_ixApi => $api )
        {
            $apiScriptEvents = $apiSubscribeEvents = [ ];

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

                    $apiSubscribeEvents[$_method] = $_eventsThrown;
                    $apiScriptEvents[$_method] = [ "$path.$_method.pre_process", "$path.$_method.post_process" ];
                }

                unset( $_operation, $_eventsThrown );
            }

            $scriptEvents[str_ireplace( '{api_name}', $apiName, $path )] = $apiScriptEvents;
            $subscribeEvents[str_ireplace( '{api_name}', $apiName, $path )] = $apiSubscribeEvents;

            unset( $apiScriptEvents, $apiSubscribeEvents, $api );
        }

        \Log::debug( '  * Discovered ' . $eventCount . ' event(s).' );

        return [ 'script' => $scriptEvents, 'subscribe' => $subscribeEvents ];
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @return array
     */
    public static function getScriptedEventMap()
    {
        if ( !empty( static::$scriptEventMap ) )
        {
            return static::$scriptEventMap;
        }

        $_encoded = \Cache::get( static::SWAGGER_SCRIPT_EVENT_CACHE_FILE );

        if ( !empty( $_encoded ) )
        {
            if ( false === ( static::$scriptEventMap = json_decode( $_encoded, true ) ) )
            {
                \Log::error( '  * Event cache appears corrupt, or cannot be read.' );
            }
        }

        //	If we still have no event map, build it.
        if ( empty( static::$scriptEventMap ) )
        {
            static::buildSwagger();
        }

        return static::$scriptEventMap;
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @return array
     */
    public static function getSubscribedEventMap()
    {
        if ( !empty( static::$subscribeEventMap ) )
        {
            return static::$subscribeEventMap;
        }

        $_encoded = \Cache::get( static::SWAGGER_SUBSCRIBE_EVENT_CACHE_FILE );

        if ( !empty( $_encoded ) )
        {
            if ( false === ( static::$subscribeEventMap = json_decode( $_encoded, true ) ) )
            {
                \Log::error( '  * Event cache appears corrupt, or cannot be read.' );
            }
        }

        //	If we still have no event map, build it.
        if ( empty( static::$subscribeEventMap ) )
        {
            static::buildSwagger();
        }

        return static::$subscribeEventMap;
    }

    /**
     * Main retrieve point for a list of swagger-able services
     * This builds the full swagger cache if it does not exist
     *
     * @return string The JSON contents of the swagger api listing.
     * @throws InternalServerErrorException
     */
    public static function getSwagger()
    {
        if ( null === ( $_content = \Cache::get( static::SWAGGER_CACHE_FILE ) ) )
        {
            static::buildSwagger();

            if ( null === $_content = \Cache::get( static::SWAGGER_CACHE_FILE ) )
            {
                throw new InternalServerErrorException( "Failed to get or create swagger cache." );
            }
        }

        return $_content;
    }

    /**
     * Main retrieve point for each service
     *
     * @param string $service Which service (api_name) to retrieve.
     *
     * @throws InternalServerErrorException
     * @return string The JSON contents of the swagger service.
     */
    public static function getSwaggerForService( $service )
    {
        $_cachePath = $service . '.json';

        if ( null === $_content = \Cache::get( $_cachePath ) )
        {
            static::buildSwagger();

            if ( null === $_content = \Cache::get( $_cachePath ) )
            {
                throw new InternalServerErrorException( "Failed to get or create swagger cache." );
            }
        }

        return $_content;
    }

    /**
     * Clears the cache produced by the swagger annotations
     */
    public static function clearCache()
    {
        \Cache::forget( static::SWAGGER_CACHE_FILE );
        \Cache::forget( static::SWAGGER_SCRIPT_EVENT_CACHE_FILE );
        \Cache::forget( static::SWAGGER_SUBSCRIBE_EVENT_CACHE_FILE );

        //  Clear the rest of the swagger cache for each service api name
        //	Spin through services and clear the cache file
        foreach ( Service::lists( 'name' ) as $service )
        {
            if ( false === \Cache::forget( $service . '.json' ) )
            {
                \Log::error( '  * System error deleting swagger cache file: ' . $service . '.json' );
                continue;
            }
        }

        //  Trigger a swagger.cache_cleared event
//        Platform::trigger( SwaggerEvents::CACHE_CLEARED );

        // rebuild swagger cache
        static::buildSwagger();
    }
}
