<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
namespace DreamFactory\Rave\Scripting;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Contracts\ScriptingEngineInterface;
use DreamFactory\Rave\Enums\ContentTypes;
use DreamFactory\Rave\Exceptions\ServiceUnavailableException;
use DreamFactory\Rave\Utility\ServiceHandler;
use DreamFactory\Rave\Utility\ResponseFactory;
use \Log;

/**
 * Allows platform access to a scripting engine
 */
abstract class BaseEngineAdapter
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int The default cache ttl, 5m = 300s
     */
    const DEFAULT_CACHE_TTL = 300;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array A list of paths where our scripts might live
     */
    protected static $libraryPaths;
    /**
     * @var array The list of registered/known libraries
     */
    protected static $libraries = [ ];
    /**
     * @var ScriptingEngineInterface The engine
     */
    protected $_engine;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     *
     * @throws ServiceUnavailableException
     */
    public function __construct( array $settings = [ ] )
    {
        //  Save off the engine
        $this->_engine = ArrayUtils::get( $settings, 'engine', $this->_engine );
    }

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return mixed
     */
    public static function startup( $options = null )
    {
        static::_initializeLibraryPaths( ArrayUtils::get( $options, 'library_paths', [ ] ) );
    }

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return mixed
     */
    public static function shutdown()
    {
        \Cache::add( 'scripting.library_paths', static::$libraryPaths, static::DEFAULT_CACHE_TTL );
        \Cache::add( 'scripting.libraries', static::$libraries, static::DEFAULT_CACHE_TTL );
    }

    /**
     * Look through the known paths for a particular script. Returns full path to script file.
     *
     * @param string $name           The name/id of the script
     * @param string $script         The name of the script
     * @param bool   $returnContents If true, the contents of the file, if found, are returned. Otherwise, the only the path is returned
     *
     * @return string
     */
    public static function loadScript( $name, $script, $returnContents = true )
    {
        //  Already read, return script
        if ( null !== ( $_script = ArrayUtils::get( static::$libraries, $name ) ) )
        {
            return $returnContents ? file_get_contents( $_script ) : $_script;
        }

        $_script = ltrim( $script, ' /' );

        //  Spin through paths and look for the script
        foreach ( static::$libraryPaths as $_path )
        {
            $_check = $_path . '/' . $_script;

            if ( is_file( $_check ) && is_readable( $_check ) )
            {
                ArrayUtils::set( static::$libraries, $name, $_check );

                return $returnContents ? file_get_contents( $_check ) : $_check;
            }
        }

        return false;
    }

    /**
     * @param array $libraryPaths
     *
     * @throws ServiceUnavailableException
     */
    protected static function _initializeLibraryPaths( $libraryPaths = null )
    {
        static::$libraryPaths = \Cache::get( 'scripting.library_paths', [ ] );
        static::$libraries = \Cache::get( 'scripting.libraries', [ ] );

        //  Add ones from constructor
        $libraryPaths = ArrayUtils::clean( $libraryPaths );

        //  Local vendor repo directory
        $libraryPaths[] = dirname( dirname( __DIR__ ) ) . '/config/scripts';

        //  Application storage script path
        $libraryPath[] = storage_path( DIRECTORY_SEPARATOR . 'scripts' );

        //  Merge in config libraries...
        $libraryPaths = array_merge( $libraryPaths, ArrayUtils::clean( \Config::get( 'dsp.scripting.paths', [ ] ) ) );

        //  Add them to collection if valid
        if ( is_array( $libraryPaths ) )
        {
            foreach ( $libraryPaths as $path )
            {
                if ( !in_array( $path, static::$libraryPaths ) )
                {
                    if ( !empty( $path ) || is_dir( $path ) || is_readable( $path ) )
                    {
                        static::$libraryPaths[] = $path;
                    }
                    else
                    {
                        Log::debug( "Invalid scripting library path given $path." );
                    }
                }
            }
        }

        \Cache::add( 'scripting.library_paths', static::$libraryPaths, static::DEFAULT_CACHE_TTL );

        if ( empty( static::$libraryPaths ) )
        {
            Log::debug( 'No scripting library paths found.' );
        }
    }

    /**
     * @return array
     */
    public static function getLibraries()
    {
        return static::$libraries;
    }

    /**
     * @return array
     */
    public static function getLibraryPaths()
    {
        return static::$libraryPaths;
    }

    /**
     * @param string $libraryPath An absolute path to a script library
     */
    public static function addLibraryPath( $libraryPath )
    {
        if ( !is_dir( $libraryPath ) || !is_readable( $libraryPath ) )
        {
            throw new \InvalidArgumentException( 'The path "' . $libraryPath . '" is invalid.' );
        }

        if ( !in_array( $libraryPath, static::$libraryPaths ) )
        {
            static::$libraryPaths[] = $libraryPath;
        }
    }

    /**
     * @param string $name   The name/id of this script
     * @param string $script The file for this script
     */
    public static function addLibrary( $name, $script )
    {
        if ( false === ( $_path = static::loadScript( $name, $script, false ) ) )
        {
            throw new \InvalidArgumentException( 'The script "' . $script . '" was not found.' );
        }
    }

    /**
     * @param string $method
     * @param string $url
     * @param mixed  $payload
     * @param array  $curlOptions
     *
     * @return \stdClass|string
     */
    protected static function _externalRequest( $method, $url, $payload = [ ], $curlOptions = [ ] )
    {
        try
        {
            $_result = Curl::request( $method, $url, $payload, $curlOptions );
        }
        catch ( \Exception $_ex )
        {
            $_result = ResponseFactory::create( $_ex, ContentTypes::PHP_ARRAY, $_ex->getCode() );

            Log::error( 'Exception: ' . $_ex->getMessage(), [ ], [ 'response' => $_result ] );
        }

        return $_result;
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $payload
     * @param array  $curlOptions Additional CURL options for external requests
     *
     * @return array
     */
    public static function inlineRequest( $method, $path, $payload = null, $curlOptions = [ ] )
    {
        if ( null === $payload || 'null' == $payload )
        {
            $payload = [ ];
        }

        if ( !empty( $curlOptions ) )
        {
            $options = [ ];

            foreach ( $curlOptions as $key => $value )
            {
                if ( !is_numeric( $key ) )
                {
                    if ( defined( $key ) )
                    {
                        $options[constant( $key )] = $value;
                    }
                }
            }

            $curlOptions = $options;
            unset( $options );
        }

        if ( 'https:/' == ( $protocol = substr( $path, 0, 7 ) ) || 'http://' == $protocol )
        {
            return static::_externalRequest( $method, $path, $payload, $curlOptions );
        }

        $result = null;
        $params = [ ];
        if ( false !== $pos = strpos( $path, '?' ) )
        {
            $paramString = substr( $path, $pos + 1 );
            if ( !empty( $paramString ) )
            {
                $pArray = explode( '&', $paramString );
                foreach ( $pArray as $k => $p )
                {
                    if ( !empty( $p ) )
                    {
                        $tmp = explode( '=', $p );
                        $name = ArrayUtils::get( $tmp, 0, $k );
                        $params[$name] = ArrayUtils::get( $tmp, 1 );
                    }
                }
            }
            $path = substr( $path, 0, $pos );
        }

        $contentType = 'application/json';

        if ( false === ( $pos = strpos( $path, '/' ) ) )
        {
            $serviceName = $path;
            $resource = null;
        }
        else
        {
            $serviceName = substr( $path, 0, $pos );
            $resource = substr( $path, $pos + 1 );

            //	Fix removal of trailing slashes from resource
            if ( !empty( $resource ) )
            {
                if ( ( false === strpos( $path, '?' ) && '/' === substr( $path, strlen( $path ) - 1, 1 ) ) ||
                     ( '/' === substr( $path, strpos( $path, '?' ) - 1, 1 ) )
                )
                {
                    $resource .= '/';
                }
            }
        }

        if ( empty( $serviceName ) )
        {
            return null;
        }

        if ( false === ( $content = json_encode( $payload, JSON_UNESCAPED_SLASHES ) ) || JSON_ERROR_NONE != json_last_error() )
        {
            $contentType = 'text/plain';
            $content = $payload;
        }

        try
        {
            $request = new ScriptServiceRequest( $method, $params );
            $request->setContent( $content, $contentType );

            //  Now set the request object and go...
            $service = ServiceHandler::getService( $serviceName );
            $result = $service->handleRequest( $request, $resource, ContentTypes::PHP_ARRAY );
        }
        catch ( \Exception $_ex )
        {
            $result = ResponseFactory::create( $_ex, ContentTypes::PHP_ARRAY, $_ex->getCode() );

            Log::error( 'Exception: ' . $_ex->getMessage(), [ ], [ 'response' => $result ] );
        }

        return $result;
    }

    /**
     * Locates and loads a library returning the contents
     *
     * @param string $id   The id of the library (i.e. "lodash", "underscore", etc.)
     * @param string $file The relative path/name of the library file
     *
     * @return string
     */
    protected static function getLibrary( $id, $file = null )
    {
        if ( null !== $file || array_key_exists( $id, static::$libraries ) )
        {
            $_file = $file ?: static::$libraries[$id];

            //  Find the library
            foreach ( static::$libraryPaths as $_name => $_path )
            {
                $_filePath = $_path . DIRECTORY_SEPARATOR . $_file;

                if ( file_exists( $_filePath ) && is_readable( $_filePath ) )
                {
                    return file_get_contents( $_filePath, 'r' );
                }
            }
        }

        throw new \InvalidArgumentException( 'The library id "' . $id . '" could not be located.' );
    }

    /**
     * @return \stdClass
     */
    protected static function getExposedApi()
    {
        static $_api;

        if ( null !== $_api )
        {
            return $_api;
        }

        $_api = new \stdClass();

        $_api->_call = function ( $method, $path, $payload = null, $curlOptions = [ ] )
        {
            return static::inlineRequest( $method, $path, $payload, $curlOptions );
        };

        $_api->get = function ( $path, $payload = null, $curlOptions = [ ] )
        {
            return static::inlineRequest( Verbs::GET, $path, $payload, $curlOptions );
        };

        $_api->put = function ( $path, $payload = null, $curlOptions = [ ] )
        {
            return static::inlineRequest( Verbs::PUT, $path, $payload, $curlOptions );
        };

        $_api->post = function ( $path, $payload = null, $curlOptions = [ ] )
        {
            return static::inlineRequest( Verbs::POST, $path, $payload, $curlOptions );
        };

        $_api->delete = function ( $path, $payload = null, $curlOptions = [ ] )
        {
            return static::inlineRequest( Verbs::DELETE, $path, $payload, $curlOptions );
        };

        $_api->merge = function ( $path, $payload = null, $curlOptions = [ ] )
        {
            return static::inlineRequest( Verbs::MERGE, $path, $payload, $curlOptions );
        };

        $_api->patch = function ( $path, $payload = null, $curlOptions = [ ] )
        {
            return static::inlineRequest( Verbs::PATCH, $path, $payload, $curlOptions );
        };

        $_api->includeScript = function ( $fileName )
        {
            $_fileName = storage_path( DIRECTORY_SEPARATOR . 'scripts' ) . DIRECTORY_SEPARATOR . $fileName;

            if ( !file_exists( $_fileName ) )
            {
                return false;
            }

            return file_get_contents( storage_path( DIRECTORY_SEPARATOR . 'scripts' ) . DIRECTORY_SEPARATOR . $fileName );
        };

        return $_api;
    }

    public static function buildPlatformAccess( $identifier )
    {
        return [
            'api'     => static::getExposedApi(),
            'config'  => \Config::all(),
            'session' => \Session::all(),
            'store'   => new ScriptSession( \Config::get( "script.$identifier.store" ), app( 'cache' ) )
        ];
    }
}