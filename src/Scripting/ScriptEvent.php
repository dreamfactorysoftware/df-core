<?php
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
    static protected $eventTemplate = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $template The name of the template to use for events. These are JSON files and reside in
     *                         [library]/config/schema
     *
     * @throws InternalServerErrorException
     * @return bool|array The schema in array form, FALSE on failure.
     */
    public static function initialize($template = self::SCRIPT_EVENT_SCHEMA)
    {
        if (false !== ($eventTemplate = \Cache::get('scripting.event_schema', false))) {
            return $eventTemplate;
        }

        //	Not cached, get it...
        $path = Platform::getLibraryConfigPath('/schema') . '/' . trim($template, ' /');

        if (is_file($path) &&
            is_readable($path) &&
            (false !== ($eventTemplate = file_get_contents($path)))
        ) {
            if (false !== ($eventTemplate = json_decode($eventTemplate, true)) &&
                JSON_ERROR_NONE == json_last_error()
            ) {
                \Cache::add('scripting.event_schema', $eventTemplate, 86400);

                return $eventTemplate;
            }
        }

        \Log::notice('Scripting unavailable. Unable to load scripting event schema: ' . $path);

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
     * sent back to the client. Some service handlers normalize the data for convenience, i.e. see
     * BaseDbSvc::_determineRequestMembers().
     *
     * Therefore the data exposed by the event system has been "normalized" to provide a reliable and consistent manner
     * in which to process said data. There should be no need for wasting time trying to determine if your data is
     * "maybe here, or maybe there, or maybe over there even" when received by your event handlers.
     *
     *
     * @param string          $eventName        The event name
     * @param PlatformEvent   $event            The event
     * @param EventDispatcher $dispatcher       The dispatcher of the event
     * @param array           $extra            Any additional data to put into the event structure
     * @param bool            $includeDspConfig If true, the current DSP config is added to container
     * @param bool            $returnJson       If true, the event will be returned as a JSON string, otherwise an
     *                                          array.
     *
     * @return array|string
     */
    public static function normalizeEvent(
        $eventName,
        PlatformEvent $event,
        $dispatcher,
        array $extra = [],
        $includeDspConfig = true,
        $returnJson = false
    ){
        static $config = null;

        if (!$config) {
            $config = ($includeDspConfig ? \Cache::get(Config::LAST_RESPONSE_CACHE_KEY, false) : false);
        }

        //	Clean up the event extras, remove data portion
        $eventExtras = $event->getData();
        $path = $dispatcher->getPathInfo(true);

        //	Clean up the trigger
        $trigger = false !== strpos($path, 'rest', 0) || false !== strpos($path, '/rest', 0) ? str_replace(
            ['/rest', 'rest'],
            null,
            $path
        ) : $path;

        $request = static::buildRequestArray($event);
        $response = $event->getResponseData();

        //	Build the array
        $event = [
            //  The event id
            'id'               => $event->getEventId(),
            //  The event name
            'name'             => $eventName,
            //  The event timestamp
            'timestamp'        => date(
                'c',
                Option::server(
                    'REQUEST_TIME_FLOAT',
                    Option::server('REQUEST_TIME', microtime(true))
                )
            ),
            //  The resource request that triggered this event
            'trigger'          => $trigger,
            //  A slightly sanitized version of the actual HTTP request URI
            'request_path'     => $path,
            //  Indicator useful to halt propagation of this event, not necessarly because of errors...
            'stop_propagation' => ArrayUtils::get($eventExtras, 'stop_propagation', false, true),
            //	Dispatcher information
            'dispatcher_id'    => spl_object_hash($dispatcher),
            'dispatcher_type'  => Inflector::neutralize(get_class($dispatcher)),
            //	Extra information passed by caller
            'extra'            => $extra,
            //	An object that contains information about the current session and the configuration of this platform
            'platform'         => [
                //  The DSP config
                'config'  => $config,
                //  The current user's session
                'session' => static::getCleanedSession(),
            ],
            /**
             * The inbound request payload as parsed by the handler for the route.
             * Any changes to this property will be made to the request parameters before the service processes the request.
             *
             * For REST events (pre an post process), this data is sourced from BasePlatformService::$requestPayload, Base
             */
            'request'          => $request,
            /**
             * The outbound response received from the service after being processed.
             * Any changes to this property will be made in the response back to the requester.
             */
            'response'         => $response,
            //	Metadata if any
        ];

        return $returnJson ? json_encode($event, JSON_UNESCAPED_SLASHES) : $event;
    }

    /**
     * Cleans up the session data to send along with an event
     *
     * @return array
     */
    protected static function getCleanedSession()
    {
        if (Pii::guest()) {
            return false;
        }

        $session = Session::getSessionData();

        if (isset($session, $session['allowed_apps'])) {
            $apps = [];

            /** @var App $app */
            foreach ($session['allowed_apps'] as $app) {
                $apps[$app->name] = $app->getAttributes();
            }

            $session['allowed_apps'] = $apps;
        }

        return $session;
    }

    /**
     * Give a normalized event, put any changed data from the payload back into the event
     *
     * @param PlatformEvent $event
     * @param array         $exposedEvent
     *
     * @return $this
     */
    public static function updateEventFromHandler(PlatformEvent &$event, array $exposedEvent = [])
    {
        //  Did propagation stop?
        if (ArrayUtils::get($exposedEvent, 'stop_propagation', false)) {
            $event->stopPropagation();
        }

        $request = ArrayUtils::getDeep($exposedEvent, 'request', 'body');
        $response = ArrayUtils::get($exposedEvent, 'response', false);

        if (!$response) {
//            Log::debug( 'No response in exposed event' );
        }

        if ($request) {
            if (!$event->isPostProcessScript()) {
                $event->setData($request);
            }

            $event->setRequestData($request);
        }

        if ($response) {
            if ($event->isPostProcessScript()) {
                $event->setData($response);
            }

            $event->setResponseData($response);
        }

        return $event;
    }

    /**
     * @return string
     */
    public static function getEventTemplate()
    {
        if (empty(static::$eventTemplate)) {
            static::initialize();
        }

        return static::$eventTemplate;
    }

    /**
     * @param string $eventTemplate
     */
    public static function setEventTemplate($eventTemplate)
    {
        static::$eventTemplate = $eventTemplate;
    }

    /**
     * @param PlatformEvent|array $event
     *
     * @return array
     */
    public static function buildRequestArray($event = null)
    {
        $reqObj = Pii::request(false);
        $data = $event ? (is_array($event) ? $event : $event->getRequestData()) : null;

        $request = [
            'method'  => strtoupper($reqObj->getMethod()),
            'headers' => $reqObj->headers->all(),
            'cookies' => $reqObj->cookies->all(),
            'query'   => $reqObj->query->all(),
            'body'    => $data,
            'files'   => false,
        ];

        $files = $reqObj->files->all();

        if (!empty($files)) {
            $request['files'] = $files;
        }

        return $request;
    }
}

//	Initialize the event template
ScriptEvent::initialize();
