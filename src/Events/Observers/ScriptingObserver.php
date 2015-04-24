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
namespace DreamFactory\Rave\Events\Observers;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Events\EventDispatcher;
use DreamFactory\Rave\Events\PlatformEvent;
use DreamFactory\Rave\Resources\System\Config;
use DreamFactory\Rave\Resources\System\Script;
use DreamFactory\Rave\Scripting\ScriptEngine;
use DreamFactory\Rave\Scripting\ScriptEvent;
use \Log;

/**
 * ScriptingObserver
 */
class ScriptingObserver extends EventObserver
{
    /**
     * @var array The scripts I am responsible for
     */
    protected $_scripts = [];

    /**
     * Process
     *
     * @param string          $eventName  The name of the event
     * @param PlatformEvent   $event      The event that occurred
     * @param EventDispatcher $dispatcher The source dispatcher
     *
     * @return mixed
     */
    public function handleEvent( $eventName, &$event = null, $dispatcher = null )
    {
        if ( !$this->isEnabled() )
        {
            return true;
        }

        //  Run scripts
        if ( null === ( $_scripts = ArrayUtils::get( $this->_scripts, $eventName ) ) )
        {
            //  See if we have a platform event handler...
            if ( false === ( $_script = Script::existsForEvent( $eventName ) ) )
            {
                $_scripts = null;
            }
        }

        if ( empty( $_scripts ) )
        {
            return true;
        }

        $_exposedEvent = $_event = ScriptEvent::normalizeEvent( $eventName, $event, $dispatcher, [] );

        foreach ( ArrayUtils::clean( $_scripts ) as $_script )
        {
            $_result = null;

            try
            {
                $_result = ScriptEngine::runScript(
                    $_script,
                    $eventName . '.js',
                    $_exposedEvent,
                    $_event['platform'],
                    $_output
                );
            }
            catch ( \Exception $_ex )
            {
                Log::error( 'Exception running script: ' . $_ex->getMessage() );
                continue;
            }

            //  The script runner should return an array
            if ( is_array( $_result ) )
            {
                ScriptEvent::updateEventFromHandler( $event, $_result );
            }

            if ( !empty( $_output ) )
            {
                Log::debug( '  * Script "' . $eventName . '.js" output: ' . $_output );
            }

            if ( $event->isPropagationStopped() )
            {
                Log::info( '  * Propagation stopped by script.' );

                return false;
            }
        }

        return true;
    }
}