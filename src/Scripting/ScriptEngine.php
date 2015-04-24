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
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Rave\Scripting;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Rave\Contracts\ScriptingEngineInterface;
use DreamFactory\Rave\Enums\ScriptLanguages;
use DreamFactory\Rave\Events\Exceptions\ScriptException;
use DreamFactory\Rave\Exceptions\ServiceUnavailableException;
use DreamFactory\Rave\Scripting\Engines\Php;
use DreamFactory\Rave\Scripting\Engines\V8Js;
use \Log;

/**
 * Scripting engine
 */
class ScriptEngine
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int The cache TTL for the scripting store
     */
    const SESSION_STORE_TTL = 60;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Array of running script engines
     */
    protected static $_instances = [ ];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Registers various available extensions to the v8 instance...
     *
     * @param array $engine_config
     * @param array $script_config
     *
     * @return ScriptingEngineInterface
     * @throws ServiceUnavailableException
     */
    public static function create( array $engine_config, $script_config = null )
    {
        $engineClass = ArrayUtils::get( $engine_config, 'class_name' );

        if ( empty( $engineClass ) || !class_exists( $engineClass ) )
        {
            throw new ServiceUnavailableException( "Failed to find script engine class '$engineClass'." );
        }

        $_engine = new $engineClass( $script_config );

        //  Stuff it in our instances array
        static::$_instances[spl_object_hash( $_engine )] = $_engine;

        return $_engine;
    }

    /**
     * Publicly destroy engine
     *
     * @param ScriptingEngineInterface $engine
     */
    public static function destroy( $engine )
    {
        $_hash = spl_object_hash( $engine );

        if ( isset( static::$_instances[$_hash] ) )
        {
            unset( static::$_instances[$_hash] );
        }

        unset( $engine );
    }

    /**
     * @param string $script        The script to run or a script file name
     * @param string $identifier    The name of this script
     * @param array  $engine_config The config of the script engine to run
     * @param array  $config        The config for this particular script
     * @param array  $data          The additional data as it will be exposed to script
     * @param string $output        Any output of the script
     *
     * @return array
     * @throws ScriptException
     * @throws ServiceUnavailableException
     */
    public static function runScript( $script, $identifier, array $engine_config, array $config = [], array &$data = [ ], &$output = null )
    {
        $_engine = static::create( $engine_config, $config );

        $_result = $_message = false;

        try
        {
            //  Don't show output
            ob_start();

            if ( is_file( $script ) )
            {
                $_result = $_engine->executeScript( $script, $identifier, $data, $config );
            }
            else
            {
                $_result = $_engine->executeString( $script, $identifier, $data, $config );
            }
        }
        catch ( ScriptException $_ex )
        {
            $_message = $_ex->getMessage();

            Log::error( $_message = "Exception executing javascript: $_message" );
        }

        //  Clean up
        $output = ob_get_clean();
        static::destroy( $_engine );

        if ( Scalar::boolval( \Config::get( 'rave.log_script_memory_usage', false ) ) )
        {
            Log::debug( 'Engine memory usage: ' . static::resizeBytes( memory_get_usage( true ) ) );
        }

        if ( false !== $_message )
        {
            throw new ScriptException( $_message, $output );
        }

        return $_result;
    }

    /**
     * Converts single bytes into proper form (kb, gb, mb, etc.) with precision 2 (i.e. 102400 > 100.00kb)
     * Found on php.net's memory_usage page
     *
     * @param int $bytes
     *
     * @return string
     */
    public static function resizeBytes( $bytes )
    {
        static $_units = [ 'b', 'kb', 'mb', 'gb', 'tb', 'pb' ];

        /** @noinspection PhpIllegalArrayKeyTypeInspection */

        return @round( $bytes / pow( 1024, ( $_i = floor( log( $bytes, 1024 ) ) ) ), 2 ) . $_units[$_i];
    }
}
