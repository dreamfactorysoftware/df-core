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
namespace DreamFactory\Rave\Contracts;

/**
 * Something that can execute scripts
 */
interface ScriptingEngineInterface
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return mixed
     */
    public static function startup( $options = null );

    /**
     * Process a single script
     *
     * @param string $script          The content of a script to execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information passed to this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeString( $script, $identifier, array $data = [ ], array $engineArguments = [ ] );

    /**
     * Process a single script from a file path
     *
     * @param string $path            The path/to/the/script to read and execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information passed to this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeScript( $path, $identifier, array $data = [ ], array $engineArguments = [ ] );

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return mixed
     */
    public static function shutdown();
}
