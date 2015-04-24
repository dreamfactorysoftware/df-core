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
     * @const string A cached events list derived from Swagger
     */
    const SWAGGER_EVENT_CACHE_FILE = '_events.json';
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
     * @var array The event map
     */
    protected static $_eventMap = false;
    /**
     * @var array The core DSP services that are built-in
     */
    protected static $_builtInServices = [
        [ 'api_name' => 'user', 'type_id' => 0, 'description' => 'User session and profile' ],
        [ 'api_name' => 'system', 'type_id' => 0, 'description' => 'System configuration' ]
    ];

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
        if ($this->request->queryBool('refresh'))
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
        static::$_eventMap = static::$_eventMap ?: [ ];

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

            if ( !isset( static::$_eventMap[$_apiName] ) || !is_array( static::$_eventMap[$_apiName] ) || empty( static::$_eventMap[$_apiName] )
            )
            {
                static::$_eventMap[$_apiName] = [ ];
            }

            $_serviceEvents = [];//static::_parseSwaggerEvents( $_apiName, $_content );

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
            static::$_eventMap[$_apiName] = array_merge(
                ArrayUtils::clean( static::$_eventMap[$_apiName] ),
                $_serviceEvents
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
                static::SWAGGER_EVENT_CACHE_FILE,
                json_encode( static::$_eventMap, JSON_UNESCAPED_SLASHES ),
                static::SWAGGER_CACHE_TTL
            )
        )
        {
            \Log::error( '  * System error creating swagger cache file: ' . static::SWAGGER_EVENT_CACHE_FILE );
        }

        \Log::info( 'Swagger cache build process complete' );

//        Platform::trigger( static::CACHE_REBUILT );

        return $_out;
    }

    public static function getStoredContentForService( Service $service )
    {
        // check the database records for custom doc in swagger, raml, etc.
        $info = $service->serviceDocs()->first();
        $content = (isset( $info)) ? $info->content : null;
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
        $_eventMap = [ ];
        $_eventCount = 0;

        foreach ( ArrayUtils::get( $data, 'apis', [ ] ) as $_ixApi => $_api )
        {
            //  Trim slashes for use as a file name
            $_scripts = $_events = [ ];

            if ( null === ( $_path = ArrayUtils::get( $_api, 'path' ) ) )
            {
                \Log::notice( '  * Missing "path" in Swagger definition: ' . $apiName );
                continue;
            }

            $_path = str_replace(
                [ '{api_name}', '/' ],
                [ $apiName, '.' ],
                trim( $_path, '/' )
            );

            foreach ( ArrayUtils::get( $_api, 'operations', [ ] ) as $_ixOps => $_operation )
            {
                if ( null !== ( $_eventNames = ArrayUtils::get( $_operation, 'event_name' ) ) )
                {
                    $_method = strtolower( ArrayUtils::get( $_operation, 'method', Verbs::GET ) );
                    $_scripts = [ ];
                    $_eventsThrown = [ ];

                    if ( is_string( $_eventNames ) && false !== strpos( $_eventNames, ',' ) )
                    {
                        $_events = explode( ',', $_eventNames );

                        //  Clean up any spaces...
                        foreach ( $_events as &$_tempEvent )
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

                        $_scripts += static::_findScripts( $_path, $_method, $_eventName );

                        $_eventsThrown[] = $_eventName;

                        //  Set actual name in swagger file
                        $data['apis'][$_ixApi]['operations'][$_ixOps]['event_name'][$_ixEventNames] = $_eventName;

                        $_eventCount++;
                    }

                    $_events[$_method] = [
                        'event'   => $_eventsThrown,
                        'scripts' => $_scripts,
                    ];
                }

                unset( $_operation, $_scripts, $_eventsThrown );
            }

            $_eventMap[str_ireplace( '{api_name}', $apiName, $_path )] = $_events;

            unset( $_scripts, $_events, $_api );
        }

        \Log::debug( '  * Discovered ' . $_eventCount . ' event(s).' );

        return $_eventMap;
    }

    /**
     * Returns a list of scripts that can response to specified events
     *
     * @param string $apiName
     * @param string $method
     * @param string $eventName Optional event name to try
     *
     * @return array|bool
     */
    protected static function _findScripts( $apiName, $method = Verbs::GET, $eventName = null )
    {
        static $_scriptPath;

        if ( empty( $_scriptPath ) )
        {
            $_scriptPath = Platform::getPrivatePath( Script::DEFAULT_SCRIPT_PATH );
        }

        //  Find standard pattern scripts: [api_name].[method].*.js
        $_scriptPattern = strtolower( $apiName ) . '.' . strtolower( $method ) . '.*.js';

        //  Check for false return...
        if ( false === ( $_scripts = FileSystem::glob( $_scriptPath . DIRECTORY_SEPARATOR . $_scriptPattern ) ) )
        {
            $_scripts = [ ];
        }

        if ( !empty( $eventName ) )
        {
            //  If an event name is given, look for the specific script [event_name].js
            $_namedScripts = FileSystem::glob( $_scriptPath . DIRECTORY_SEPARATOR . $eventName . '.js' );

            //  Finally, glob any placeholders that are left in the event name...
            $_globbed = preg_replace( '/({.*})/', '*', $eventName );
            $_globbedScripts = FileSystem::glob( $_scriptPath . DIRECTORY_SEPARATOR . $_globbed . '.js' );

            $_scripts = array_merge( $_scripts, $_globbedScripts, $_namedScripts );
        }

        if ( empty( $_scripts ) )
        {
            if ( !empty( $eventName ) )
            {
                $_scripts = FileSystem::glob( $_scriptPath . DIRECTORY_SEPARATOR . $eventName . '.js' );
            }

            if ( empty( $_scripts ) )
            {
                return [ ];
            }
        }

        $_response = [ ];
        $_eventPattern = '/^' . str_replace( [ '.*.js', '.' ], [ null, '\\.' ], $_scriptPattern ) . '\\.(\w)\\.js$/i';

        foreach ( $_scripts as $_script )
        {
            if ( 0 === preg_match( $_eventPattern, $_script ) )
            {
                $_response[] = $_script;
            }
        }

        return $_response;
    }

    /**
     * @param BaseRestService $service
     * @param string          $method
     * @param string          $eventName                 Global search for event name
     * @param array           $replacementValues         An optional array of replacements to consider in event name
     *                                                   matching
     *
     * @return string
     */
    public static function findEvent( BaseRestService $service, $method, $eventName = null, array $replacementValues = [ ] )
    {
        $_cache = \Cache::get( 'swagger.event_map_cache', [ ] );

        $_map = static::getEventMap();
        $_aliases = $service->getVerbAliases();
        $_methods = [ $method ];

        foreach ( ArrayUtils::clean( $_aliases ) as $_action => $_alias )
        {
            if ( $method == $_alias )
            {
                $_methods[] = $_action;
            }
        }

        //  Change the request uri for inline calls
        $_requestUri = ArrayUtils::server( 'INLINE_REQUEST_URI', Pii::request( false )->getRequestUri() );

        $_hash = sha1( $method . '.' . $_requestUri );

        if ( isset( $_cache[$_hash] ) )
        {
            return $_cache[$_hash];
        }

        //  Global search by name
        if ( null !== $eventName )
        {
            foreach ( $_map as $_path )
            {
                foreach ( $_path as $_method => $_info )
                {
                    if ( 0 !== strcasecmp( $_method, $method ) )
                    {
                        continue;
                    }

                    if ( 0 == strcasecmp( $eventName, $_eventName = ArrayUtils::get( $_info, 'event' ) ) )
                    {
                        $_cache[$_hash] = $_eventName;

                        return true;
                    }
                }
            }

            return false;
        }

        $_apiName = strtolower( $service->getApiName() );
        $_savedResource = $_resource = $service->getResource();

        /** @noinspection PhpUndefinedMethodInspection */
        $_resourceId = method_exists( $service, 'getResourceId' ) ? @$service->getResourceId() : null;

        $_pathParts = explode(
            '/',
            ltrim(
                str_ireplace(
                    'rest',
                    null,
                    trim( !Pii::cli() ? Pii::request( true )->getPathInfo() : $service->getResourcePath(), '/' )
                ),
                '/'
            )
        );

        if ( empty( $_resource ) )
        {
            $_resource = $_apiName;
        }
        else
        {
            switch ( $_apiName )
            {
                case 'db':
                    $_resource = 'db';
                    break;

                default:
                    if ( $_resource != ( $_requested = ArrayUtils::get( $_pathParts, 0 ) ) )
                    {
                        $_resource = $_requested;
                    }
                    break;
            }
        }

        if ( null === ( $_resources = ArrayUtils::get( $_map, $_resource ) ) )
        {
            if ( !method_exists( $service, 'getServiceName' ) || null === ( $_resources = ArrayUtils::get( $_map, $service->getServiceName() ) )
            )
            {
                if ( null === ( $_resources = ArrayUtils::get( $_map, 'system' ) ) )
                {
                    return null;
                }
            }
        }

        $_path = !Pii::cli() ? Pii::request( true )->getPathInfo() : $service->getResourcePath();

        if ( 'rest' == substr( $_path, 0, 4 ) )
        {
            $_path = substr( $_path, 4 );
        }

        $_path = trim( $_path, '/' );

        //  Strip off the resource ID if any...
        if ( $_resourceId && false !== ( $_pos = stripos( $_path, '/' . $_resourceId ) ) )
        {
            $_path = substr( $_path, 0, $_pos );
        }

        $_swaps = [ [ ], [ ] ];

        switch ( $service->getTypeId() )
        {
            case PlatformServiceTypes::LOCAL_SQL_DB:
            case PlatformServiceTypes::LOCAL_SQL_DB_SCHEMA:
            case PlatformServiceTypes::REMOTE_SQL_DB:
            case PlatformServiceTypes::REMOTE_SQL_DB_SCHEMA:
            case PlatformServiceTypes::NOSQL_DB:
                $_swaps = [
                    [
                        $_savedResource,
                    ],
                    [
                        '{table_name}',
                    ],
                ];

                $_path = str_ireplace( $_swaps[0], $_swaps[1], $_path );
                break;

            case PlatformServiceTypes::LOCAL_FILE_STORAGE:
            case PlatformServiceTypes::REMOTE_FILE_STORAGE:
                if ( $service instanceof BaseFileSvc )
                {
                    $_swaps = [
                        [
                            ArrayUtils::get( $replacementValues, 'container', $service->getContainerId() ),
                            $_folderPath = ArrayUtils::get( $replacementValues, 'folder_path', $service->getFolderPath() ),
                            $_filePath = ArrayUtils::get( $replacementValues, 'file_path', $service->getFilePath() ),
                        ],
                        [
                            '{container}',
                            '{folder_path}',
                            '{file_path}',
                        ],
                    ];

                    //  Add in optional trailing slashes
                    if ( $_folderPath )
                    {
                        $_swaps[0][] = $_folderPath[strlen( $_folderPath ) - 1] != '/'
                            ? $_folderPath . '/'
                            : substr(
                                $_folderPath,
                                0,
                                strlen( $_folderPath ) - 1
                            );
                        $_swaps[1][] = '{folder_path}';
                    }

                    if ( $_filePath )
                    {
                        $_swaps[0][] = $_filePath[strlen( $_filePath ) - 1] != '/'
                            ? $_filePath . '/'
                            : substr(
                                $_filePath,
                                0,
                                strlen( $_filePath ) - 1
                            );
                        $_swaps[1][] = '{file_path}';
                    }
                }

                $_path = str_ireplace( $_swaps[0], $_swaps[1], $_path );
                break;
        }

        if ( empty( $_path ) )
        {
            return null;
        }

        $_path = implode( '.', explode( '/', ltrim( $_path, '/' ) ) );
        $_pattern = '#^' . preg_replace( '/\\\:[a-zA-Z0-9\_\-]+/', '([a-zA-Z0-9\-\_]+)', preg_quote( $_path ) ) . '/?$#';
        $_matches = preg_grep( $_pattern, array_keys( $_resources ) );

        if ( empty( $_matches ) )
        {
            return null;
        }

        foreach ( $_matches as $_match )
        {
            foreach ( $_methods as $_method )
            {
                if ( null === ( $_methodInfo = ArrayUtils::getDeep( $_resources, $_match, $_method ) ) )
                {
                    continue;
                }

                if ( null !== ( $_eventName = ArrayUtils::get( $_methodInfo, 'event' ) ) )
                {
                    //  Restore the original path...
                    $_eventName = str_ireplace( $_swaps[1], $_swaps[0], $_eventName );

                    $_cache[$_hash] = $_eventName;

                    //  Cache for one minute...
                    \Cache::add( 'swagger.event_map_cache', $_cache, 60 );

                    return $_eventName;
                }
            }
        }

        return null;
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @return array
     */
    public static function getEventMap()
    {
        if ( !empty( static::$_eventMap ) )
        {
            return static::$_eventMap;
        }

        $_encoded = \Cache::get( static::SWAGGER_EVENT_CACHE_FILE );

        if ( !empty( $_encoded ) )
        {
            if ( false === ( static::$_eventMap = json_decode( $_encoded, true ) ) )
            {
                \Log::error( '  * Event cache appears corrupt, or cannot be read.' );
            }
        }

        //	If we still have no event map, build it.
        if ( empty( static::$_eventMap ) )
        {
            static::buildSwagger();
        }

        return static::$_eventMap;
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
        \Cache::forget( static::SWAGGER_EVENT_CACHE_FILE );

        //  Clear the rest of the swagger cache for each service api name
        //	Spin through services and clear the cache file
        foreach ( Service::lists('name') as $service )
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
