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
namespace DreamFactory\Rave\Scripting\Engines;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Contracts\ScriptingEngineInterface;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\RestException;
use DreamFactory\Rave\Exceptions\ServiceUnavailableException;
use DreamFactory\Rave\Scripting\BaseEngineAdapter;
use DreamFactory\Rave\Scripting\ScriptSession;
use \Log;

/**
 * Plugin for the php-v8js extension which exposes the V8 Javascript engine
 */
class V8Js extends BaseEngineAdapter implements ScriptingEngineInterface
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The name of the object which exposes PHP
     */
    const EXPOSED_OBJECT_NAME = 'DSP';
    /**
     * @type string The template for all module loading
     */
    const MODULE_LOADER_TEMPLATE = 'require("{module}");';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var bool True if system version of V8Js supports module loading
     */
    protected static $_moduleLoaderAvailable = false;
    /**
     * @var bool True if system version of V8Js supports module loading
     */
    protected static $_logScriptMemoryUsage = false;
    /**
     * @var \ReflectionClass
     */
    protected static $_mirror;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     *
     * @throws ServiceUnavailableException
     */
    public function __construct( $settings = null )
    {
        parent::__construct();

        if ( !extension_loaded( 'v8js' ) )
        {
            throw new ServiceUnavailableException( "This DSP cannot run server-side javascript scripts. The 'v8js' is not available." );
        }

        $settings = ArrayUtils::clean( $settings );
        $name = ArrayUtils::get( $settings, 'name', self::EXPOSED_OBJECT_NAME, true );
        $variables = ArrayUtils::get( $settings, 'variables', [ ], true );
        $extensions = ArrayUtils::get( $settings, 'extensions', [ ], true );
        $reportUncaughtExceptions = ArrayUtils::getBool( $settings, 'report_uncaught_exceptions', false );
        $logMemoryUsage = ArrayUtils::getBool( $settings, 'log_memory_usage', false );

        static::startup( $settings );

        //  Set up our script mappings for module loading
        /** @noinspection PhpUndefinedClassInspection */
        $this->_engine = new \V8Js( $name, $variables, $extensions, $reportUncaughtExceptions );

        /**
         * This is the callback for the exposed "require()" function in the sandbox
         */
        if ( static::$_moduleLoaderAvailable )
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->_engine->setModuleLoader(
                function ( $module )
                {
                    return static::loadScriptingModule( $module );
                }
            );
        }
        else
        {
            /** @noinspection PhpUndefinedClassInspection */
            Log::debug( '  * no "require()" support in V8 library v' . \V8Js::V8_VERSION );
        }

        if ( $logMemoryUsage )
        {
            /** @noinspection PhpUndefinedMethodInspection */
            $_loadedExtensions = $this->_engine->getExtensions();

            Log::debug(
                '  * engine created with the following extensions: ' .
                ( !empty( $_loadedExtensions ) ? implode( ', ', array_keys( $_loadedExtensions ) ) : '**NONE**' )
            );
        }
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
        parent::startup( $options );

        //	Find out if we have support for "require()"
        $_mirror = new \ReflectionClass( '\\V8Js' );

        /** @noinspection PhpUndefinedMethodInspection */
        if ( false !== ( static::$_moduleLoaderAvailable = $_mirror->hasMethod( 'setModuleLoader' ) ) )
        {
        }

        //  Register any extensions
        if ( null !== $extensions = ArrayUtils::get( $options, 'extensions', [ ], true ) )
        {
            static::_registerExtensions( ArrayUtils::clean( $extensions ) );
        }
    }

    /**
     * Called before script is executed so you can wrap the script and add injections
     *
     * @param string $script
     * @param array  $normalizedEvent
     *
     * @return string
     */
    protected function _wrapScript( $script, array $normalizedEvent = [ ] )
    {
        $_wrappedScript = <<<JS

_result = (function() {
	//	The event information
	//noinspection JSUnresolvedVariable
	var _event = DSP.event;

	return (function(event) {
		var _scriptResult = (function(event) {
			//noinspection BadExpressionStatementJS,JSUnresolvedVariable
			{$script};
		})(_event);

		if ( _event ) {
			_event.script_result = _scriptResult;
		}

		return _event;
	})(_event);
})();

JS;

        if ( !static::$_moduleLoaderAvailable )
        {
            $_wrappedScript = \Cache::get( 'scripting.module.lodash', static::loadScriptingModule( 'lodash', false ), false, 3600 ) . ';' . $_wrappedScript;
        }

        return $_wrappedScript;
    }

    /**
     * Process a single script
     *
     * @param string $script          The string to execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of data to be passed to this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeString( $script, $identifier, array $data = [ ], array $engineArguments = [ ] )
    {
        $exposedPlatform = [
            //            'config'  => Config::getCurrentConfig(),
            //            'session' => Session::getSessionData()
        ];

        try
        {
            $_runnerShell = $this->enrobeScript( $script, $data, $exposedPlatform );

            //  Don't show output
            ob_start();

            /** @noinspection PhpUndefinedMethodInspection */
            /** @noinspection PhpUndefinedClassInspection */
            return $this->_engine->executeString( $_runnerShell, $identifier, \V8Js::FLAG_FORCE_ARRAY );
        }
            /** @noinspection PhpUndefinedClassInspection */
        catch ( \V8JsException $_ex )
        {
            $_message = $_ex->getMessage();

            /**
             * @note     V8JsTimeLimitException was released in a later version of the libv8 library than is supported by the current PECL v8js extension. Hence the check below.
             * @noteDate 2014-04-03
             */

            /** @noinspection PhpUndefinedClassInspection */
            if ( class_exists( '\\V8JsTimeLimitException', false ) && ( $_ex instanceof \V8JsTimeLimitException ) )
            {
                /** @var \Exception $_ex */
                Log::error( $_message = "Timeout while running script '$identifier': $_message" );
            }
            else
            {
                /** @noinspection PhpUndefinedClassInspection */
                if ( class_exists( '\\V8JsMemoryLimitException', false ) && $_ex instanceof \V8JsMemoryLimitException )
                {
                    Log::error( $_message = "Out of memory while running script '$identifier': $_message" );
                }
                else
                {
                    Log::error( $_message = "Exception executing javascript: $_message" );
                }
            }
        }
            /** @noinspection PhpUndefinedClassInspection */
        catch ( \V8JsScriptException $_ex )
        {
            $_message = $_ex->getMessage();

            /**
             * @note     V8JsTimeLimitException was released in a later version of the libv8 library than is supported by the current PECL v8js extension. Hence the check below.
             * @noteDate 2014-04-03
             */

            /** @noinspection PhpUndefinedClassInspection */
            if ( class_exists( '\\V8JsTimeLimitException', false ) && ( $_ex instanceof \V8JsTimeLimitException ) )
            {
                /** @var \Exception $_ex */
                Log::error( $_message = "Timeout while running script '$identifier': $_message" );
            }
            else
            {
                /** @noinspection PhpUndefinedClassInspection */
                if ( class_exists( '\\V8JsMemoryLimitException', false ) && $_ex instanceof \V8JsMemoryLimitException )
                {
                    Log::error( $_message = "Out of memory while running script '$identifier': $_message" );
                }
                else
                {
                    Log::error( $_message = "Exception executing javascript: $_message" );
                }
            }
        }
    }

    /**
     * Process a single script
     *
     * @param string $path            The path/to/the/script to read and execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeScript( $path, $identifier, array $data = [ ], array $engineArguments = [ ] )
    {
        return $this->executeString( static::loadScript( $identifier, $path, true ), $identifier, $data, $engineArguments );
    }

    /**
     * @param string $module      The name of the module to load
     *
     * @throws \DreamFactory\Rave\Exceptions\InternalServerErrorException
     * @return mixed
     */
    public static function loadScriptingModule( $module )
    {
        $_fullScriptPath = false;

        //  Remove any quotes from this passed in module
        $module = trim( str_replace( [ "'", '"' ], null, $module ), ' /' );

        //  Check the configured script paths
        if ( null === ( $_script = ArrayUtils::get( static::$_libraries, $module ) ) )
        {
            $_script = $module;
        }

        foreach ( static::$_libraryPaths as $_key => $_path )
        {
            $_checkScriptPath = $_path . DIRECTORY_SEPARATOR . $_script;

            if ( is_file( $_checkScriptPath ) && is_readable( $_checkScriptPath ) )
            {
                $_fullScriptPath = $_checkScriptPath;
                break;
            }
        }

        if ( !$_script || !$_fullScriptPath )
        {
            throw new InternalServerErrorException(
                'The module "' . $module . '" could not be found in any known locations.'
            );
        }

        return file_get_contents( $_fullScriptPath );
    }

    /**
     * @param array $libraryPaths
     *
     * @throws RestException
     */
    protected static function _initializeLibraryPaths( $libraryPaths = null )
    {
        //  Get our script path
        static::$_libraryScriptPath = storage_path( DIRECTORY_SEPARATOR . 'scripting/v8js/scripts/' );

        if ( empty( static::$_libraryScriptPath ) || !is_dir( static::$_libraryScriptPath ) )
        {
            throw new ServiceUnavailableException( 'This service is not available. Storage path and/or required libraries not available.' );
        }

//        static::$_logScriptMemoryUsage = \Config::get( 'dsp.log_script_memory_usage', false );

        //  Merge in user libraries...
        static::$_userLibraries = array_merge( static::$_userLibraries, \Config::get( 'dsp.scripting.user_libraries', [ ] ) );

        //  All the paths that we will check for scripts in order
        static::$_libraryPaths = [
            'library' => static::$_libraryScriptPath,
            //            //  This is ONLY the root of the app store (storage/applications)
            //            'app'      => Platform::getApplicationsPath(),
            //            //  Now check library distribution (vendor/dreamfactory/lib-php-common-platform/config/scripts)
            //            'library'  => static::$_libraryScriptPath,
            //            //  This is the private event scripting area used by the admin console (storage/.private/scripts)
            //            'storage'  => Platform::getPrivatePath( DIRECTORY_SEPARATOR . 'scripts' ),
            //            //  This is the private user scripting area used by the admin console (storage/.private/scripts.user)
            //            'user'     => Platform::getPrivatePath( DIRECTORY_SEPARATOR . 'scripts.user' ),
            //            //  Scripts here override library scripts (config/scripts)
            //            'platform' => Platform::getPlatformConfigPath(DIRECTORY_SEPARATOR . 'scripts'),
            //            //  Static libraries included with the distribution (web/static)
            //            'static'   => dirname( Platform::getPlatformConfigPath() ) . DIRECTORY_SEPARATOR . 'web' . DIRECTORY_SEPARATOR . 'static',
        ];
    }

    /**
     * Registers all distribution library modules as extensions.
     * These can be accessed from scripts like this:
     *
     * require("lodash");
     *
     * var a = [ 'one', 'two', 'three' ];
     *
     * _.each( a, function( element ) {
     *      print( "Found " + element + " in array\n" );
     * });
     *
     * Please note that this requires a version of the V8 library above any that are currently
     * distributed with popular distributions. As such, if this feature is not available
     * (module loading), the "lodash" library will be automatically registered and injected
     * into all script contexts.
     *
     * @return array|bool
     */
    protected static function _registerExtensions( array $extensions = [ ] )
    {
        $_registered = [ ];

        foreach ( $extensions as $_module )
        {
            /** @noinspection PhpUndefinedClassInspection */
            \V8Js::registerExtension( $_module, static::loadScriptingModule( $_module ), [ ], false );
        }

        return empty( $_registered ) ? false : $_registered;
    }

    /**
     * @param string $script
     * @param array  $data
     * @param array  $platform
     *
     * @throws \DreamFactory\Rave\Exceptions\InternalServerErrorException
     * @return string
     */
    public function enrobeScript( $script, array $data = [ ], array $platform = [ ] )
    {
        $data['__tag__'] = 'exposed_event';
        $platform['api'] = static::_getExposedApi();
        // todo what is app.run_id?
        $platform['store'] = new ScriptSession( \Config::get( 'app.run_id' ), app( 'cache' ) );

        $this->_engine->event = $data;
        $this->_engine->platform = $platform;

        $_jsonEvent = json_encode( $data, JSON_UNESCAPED_SLASHES );

        //  Load user libraries
        $_userLibraries = \Cache::get( 'scripting.libraries.user', static::_loadUserLibraries(), false, 3600 );

        $_enrobedScript = <<<JS

//noinspection BadExpressionStatementJS
{$_userLibraries};

_wrapperResult = (function() {

    //noinspection JSUnresolvedVariable
    var _event = {$_jsonEvent};

	try	{
        //noinspection JSUnresolvedVariable
        _event.script_result = (function(event, platform) {

            //noinspection CoffeeScriptUnusedLocalSymbols,JSUnusedLocalSymbols
            var include = function( fileName ) {
                var _contents;

                //noinspection JSUnresolvedFunction
            if ( false === ( _contents = platform.api.includeUserScript(fileName) ) ) {
                    throw 'User script "' + fileName + '" not found.';
                }

                return _contents;
            };

            //noinspection BadExpressionStatementJS,JSUnresolvedVariable
            {$script};
    	})(_event, DSP.platform);
	}
	catch ( _ex ) {
		_event.script_result = {error:_ex.message};
		_event.exception = _ex;
	}

	return _event;

})();

JS;

        if ( !static::$_moduleLoaderAvailable )
        {
            $_enrobedScript = \Cache::get( 'scripting.modules.lodash', static::loadScriptingModule( 'lodash', false ), false, 3600 ) . ';' . $_enrobedScript;
        }

        return $_enrobedScript;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call( $name, $arguments )
    {
        if ( $this->_engine )
        {
            return call_user_func_array( [ $this->_engine, $name ], $arguments );
        }

        return null;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public static function __callStatic( $name, $arguments )
    {
        return call_user_func_array( [ '\\V8Js', $name ], $arguments );
    }

}