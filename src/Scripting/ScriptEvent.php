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
namespace DreamFactory\Core\Scripting;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Events\EventDispatcher;
use DreamFactory\Core\Events\PlatformEvent;
use DreamFactory\Core\Resources\System\Config;
use DreamFactory\Core\Resources\User\Session;
use DreamFactory\Core\Utility\Platform;
use DreamFactory\Core\Yii\Models\App;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Acts as a proxy between a DSP PHP $event and a server-side script
 */
class ScriptEvent
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The name of the script event schema file
     */
    const SCRIPT_EVENT_SCHEMA = 'script_event_schema.json';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The event schema for scripting events
     */
    static protected $_eventTemplate = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $template The name of the template to use for events. These are JSON files and reside in [library]/config/schema
     *
     * @throws InternalServerErrorException
     * @return bool|array The schema in array form, FALSE on failure.
     */
    public static function initialize( $template = self::SCRIPT_EVENT_SCHEMA )
    {
        if ( false !== ( $_eventTemplate = \Cache::get( 'scripting.event_schema', false ) ) )
        {
            return $_eventTemplate;
        }

        //	Not cached, get it...
        $_path = Platform::getLibraryConfigPath( '/schema' ) . '/' . trim( $template, ' /' );

        if ( is_file( $_path ) &&
             is_readable( $_path ) &&
             ( false !== ( $_eventTemplate = file_get_contents( $_path ) ) )
        )
        {
            if ( false !== ( $_eventTemplate = json_decode( $_eventTemplate, true ) ) &&
                 JSON_ERROR_NONE == json_last_error()
            )
            {
                \Cache::add( 'scripting.event_schema', $_eventTemplate, 86400 );

                return $_eventTemplate;
            }
        }

        \Log::notice( 'Scripting unavailable. Unable to load scripting event schema: ' . $_path );

        return false;
    }

    /**
     * Creates a generic, consistent event for scripting and notifications
     *
     * The returned array is as follows:
     *
     * array(
     *  //    Basics
     *  'id'                => 'A unique ID assigned to this event',
     *  'name'              => 'event.name',
     *  'trigger'           => '{api_name}/{resource}',
     *  'stop_propagation'  => [true|false],
     *  'dispatcher'        => array(
     *      'id'            => 'A unique ID assigned to the dispatcher of this event',
     *      'type'          => 'The class name of the dispatcher',
     *  ),
     *  //  Information about the triggering request
     *  'request'           => array(
     *      'timestamp'     => 'timestamp of the initial request',
     *      'path'          => '/full/path/that/triggered/event',
     *      'api_name'      =>'The api_name of the called service',
     *      'resource'      => 'The name of the resource requested',
     *      'body'          => 'The body posted as part of the request (possibly normalized by the service)',
     *  ),
     *  //  Information about the outgoing response.
     *  'response' => 'The response body returned to the calling service and eventually to the requesting client.',
     *  //    Access to the platform api
     *  'platform'      => array(
     *      'api'       => [wormhole to inline-REST API],
     *      'config'    => [standard DSP configuration update],
     *      'session'   => [the current session],
     *  ),
     *  'extra' => [Extra information passed by caller],
     * )
     *
     * Note that this structure is not passed to the script verbatim. Portions are extracted and exposed by the
     * Script resource as it sees fit.
     *
     * Please note that the format of the request and response bodies may differ slightly from the format passed in or
     * sent back to the client. Some service handlers normalize the data for convenience, i.e. see BaseDbSvc::_determineRequestMembers().
     *
     * Therefore the data exposed by the event system has been "normalized" to provide a reliable and consistent manner in which to process said data.
     * There should be no need for wasting time trying to determine if your data is "maybe here, or maybe there, or maybe over there even" when received by
     * your event handlers.
     *
     *
     * @param string          $eventName        The event name
     * @param PlatformEvent   $event            The event
     * @param EventDispatcher $dispatcher       The dispatcher of the event
     * @param array           $extra            Any additional data to put into the event structure
     * @param bool            $includeDspConfig If true, the current DSP config is added to container
     * @param bool            $returnJson       If true, the event will be returned as a JSON string, otherwise an array.
     *
     * @return array|string
     */
    public static function normalizeEvent( $eventName, PlatformEvent $event, $dispatcher, array $extra = [], $includeDspConfig = true, $returnJson = false )
    {
        static $_config = null;

        if ( !$_config )
        {
            $_config = ( $includeDspConfig ? \Cache::get( Config::LAST_RESPONSE_CACHE_KEY, false ) : false );
        }

        //	Clean up the event extras, remove data portion
        $_eventExtras = $event->getData();
        $_path = $dispatcher->getPathInfo( true );

        //	Clean up the trigger
        $_trigger = false !== strpos( $_path, 'rest', 0 ) || false !== strpos( $_path, '/rest', 0 ) ? str_replace(
            [ '/rest', 'rest' ],
            null,
            $_path
        ) : $_path;

        $_request = static::buildRequestArray( $event );
        $_response = $event->getResponseData();

        //	Build the array
        $_event = [
            //  The event id
            'id'               => $event->getEventId(),
            //  The event name
            'name'             => $eventName,
            //  The event timestamp
            'timestamp'        => date(
                'c',
                Option::server(
                    'REQUEST_TIME_FLOAT',
                    Option::server( 'REQUEST_TIME', microtime( true ) )
                )
            ),
            //  The resource request that triggered this event
            'trigger'          => $_trigger,
            //  A slightly sanitized version of the actual HTTP request URI
            'request_path'     => $_path,
            //  Indicator useful to halt propagation of this event, not necessarly because of errors...
            'stop_propagation' => ArrayUtils::get( $_eventExtras, 'stop_propagation', false, true ),
            //	Dispatcher information
            'dispatcher_id'    => spl_object_hash( $dispatcher ),
            'dispatcher_type'  => Inflector::neutralize( get_class( $dispatcher ) ),
            //	Extra information passed by caller
            'extra'            => $extra,
            //	An object that contains information about the current session and the configuration of this platform
            'platform'         => [
                //  The DSP config
                'config'  => $_config,
                //  The current user's session
                'session' => static::_getCleanedSession(),
            ],
            /**
             * The inbound request payload as parsed by the handler for the route.
             * Any changes to this property will be made to the request parameters before the service processes the request.
             *
             * For REST events (pre an post process), this data is sourced from BasePlatformService::$requestPayload, Base
             */
            'request'          => $_request,
            /**
             * The outbound response received from the service after being processed.
             * Any changes to this property will be made in the response back to the requester.
             */
            'response'         => $_response,
            //	Metadata if any
        ];

        return $returnJson ? json_encode( $_event, JSON_UNESCAPED_SLASHES ) : $_event;
    }

    /**
     * Cleans up the session data to send along with an event
     *
     * @return array
     */
    protected static function _getCleanedSession()
    {
        if ( Pii::guest() )
        {
            return false;
        }

        $_session = Session::getSessionData();

        if ( isset( $_session, $_session['allowed_apps'] ) )
        {
            $_apps = [];

            /** @var App $_app */
            foreach ( $_session['allowed_apps'] as $_app )
            {
                $_apps[ $_app->name ] = $_app->getAttributes();
            }

            $_session['allowed_apps'] = $_apps;
        }

        return $_session;
    }

    /**
     * Give a normalized event, put any changed data from the payload back into the event
     *
     * @param PlatformEvent $event
     * @param array         $exposedEvent
     *
     * @return $this
     */
    public static function updateEventFromHandler( PlatformEvent &$event, array $exposedEvent = [] )
    {
        //  Did propagation stop?
        if ( ArrayUtils::get( $exposedEvent, 'stop_propagation', false ) )
        {
            $event->stopPropagation();
        }

        $_request = ArrayUtils::getDeep( $exposedEvent, 'request', 'body' );
        $_response = ArrayUtils::get( $exposedEvent, 'response', false );

        if ( !$_response )
        {
//            Log::debug( 'No response in exposed event' );
        }

        if ( $_request )
        {
            if ( !$event->isPostProcessScript() )
            {
                $event->setData( $_request );
            }

            $event->setRequestData( $_request );
        }

        if ( $_response )
        {
            if ( $event->isPostProcessScript() )
            {
                $event->setData( $_response );
            }

            $event->setResponseData( $_response );
        }

        return $event;
    }

    /**
     * @return string
     */
    public static function getEventTemplate()
    {
        if ( empty( static::$_eventTemplate ) )
        {
            static::initialize();
        }

        return static::$_eventTemplate;
    }

    /**
     * @param string $eventTemplate
     */
    public static function setEventTemplate( $eventTemplate )
    {
        static::$_eventTemplate = $eventTemplate;
    }

    /**
     * @param PlatformEvent|array $event
     *
     * @return array
     */
    public static function buildRequestArray( $event = null )
    {
        $_reqObj = Pii::request( false );
        $_data = $event ? ( is_array( $event ) ? $event : $event->getRequestData() ) : null;

        $_request = [
            'method'  => strtoupper( $_reqObj->getMethod() ),
            'headers' => $_reqObj->headers->all(),
            'cookies' => $_reqObj->cookies->all(),
            'query'   => $_reqObj->query->all(),
            'body'    => $_data,
            'files'   => false,
        ];

        $_files = $_reqObj->files->all();

        if ( !empty( $_files ) )
        {
            $_request['files'] = $_files;
        }

        return $_request;
    }
}

//	Initialize the event template
ScriptEvent::initialize();
