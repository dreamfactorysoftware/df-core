<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Enums\ApiDocFormatTypes;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\CacheUtilities;

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
    const EVENT_CACHE_KEY = 'events';
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
    protected static $eventMap = false;

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
        \Log::info('Building event cache');

        //  Build event mapping from services in database

        //	Initialize the event map
        $processEventMap = [];
        $broadcastEventMap = [];

        //  Pull any custom swagger docs
        $result = Service::with(
            [
                'serviceDocs' => function ($query){
                    $query->where('format', ApiDocFormatTypes::SWAGGER);
                }
            ]
        )->get();

        //	Spin through services and pull the configs
        $services = [];
        foreach ($result as $service) {
            $apiName = $service->name;
            $content = static::getStoredContentForService($service);

            if (empty($content)) {
                \Log::info('  * No Swagger content found for service "' . $apiName . '"');
                continue;
            }

            $serviceEvents = static::_parseSwaggerEvents($apiName, $content);

            // build main services list
            $services[] = [
                'path'        => '/' . $apiName,
                'description' => $service->description
            ];

            //	Parse the events while we get the chance...
            $processEventMap[$apiName] = ArrayUtils::get($serviceEvents, 'process', []);
            $broadcastEventMap[$apiName] = ArrayUtils::get($serviceEvents, 'broadcast', []);

            unset($content, $filePath, $service, $serviceEvents);
        }

        static::$eventMap = ['process' => $processEventMap, 'broadcast' => $broadcastEventMap];
        //	Write event cache file
        if (false === CacheUtilities::put(
                static::EVENT_CACHE_KEY,
                static::$eventMap,
                static::CACHE_TTL
            )
        ) {
            \Log::error('  * System error creating swagger event cache file: ' .
                static::EVENT_CACHE_KEY);
        }

        \Log::info('Event cache build process complete');
//        Platform::trigger( static::CACHE_REBUILT );
    }

    public static function getStoredContentForServiceByName($name)
    {
        if (!is_string($name)) {
            throw new BadRequestException("Could not find a service for $name");
        }

        $service = Service::whereName($name)->get()->first();
        if (empty($service)) {
            throw new NotFoundException("Could not find a service for $name");
        }

        return static::getStoredContentForService($service);
    }

    public static function getStoredContentForService(Service $service)
    {
        // check the database records for custom doc in swagger, raml, etc.
        $info = $service->serviceDocs()->first();
        $content = (isset($info)) ? $info->content : null;
        if (is_string($content)) {
            $content = json_decode($content, true);
        } else {
            $serviceClass = $service->serviceType()->first()->class_name;
            $settings = $service->toArray();

            /** @var BaseRestService $obj */
            $obj = new $serviceClass($settings);
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
    protected static function _parseSwaggerEvents($apiName, &$data)
    {
        $processEvents = [];
        $broadcastEvents = [];
        $eventCount = 0;

        foreach (ArrayUtils::get($data, 'apis', []) as $ixApi => $api) {
            $apiProcessEvents = [];
            $apiBroadcastEvents = [];

            if (null === ($path = ArrayUtils::get($api, 'path'))) {
                \Log::notice('  * Missing "path" in Swagger definition: ' . $apiName);
                continue;
            }

            $path = str_replace(
                ['{api_name}', '/'],
                [$apiName, '.'],
                trim($path, '/')
            );

            foreach (ArrayUtils::get($api, 'operations', []) as $ixOps => $operation) {
                if (null !== ($eventNames = ArrayUtils::get($operation, 'event_name'))) {
                    $method = strtolower(ArrayUtils::get($operation, 'method', Verbs::GET));
                    $eventsThrown = [];

                    if (is_string($eventNames) && false !== strpos($eventNames, ',')) {
                        $eventNames = explode(',', $eventNames);

                        //  Clean up any spaces...
                        foreach ($eventNames as &$tempEvent) {
                            $tempEvent = trim($tempEvent);
                        }
                    }

                    if (empty($eventNames)) {
                        $eventNames = [];
                    } else if (!is_array($eventNames)) {
                        $eventNames = [$eventNames];
                    }

                    //  Set into master record
                    $data['apis'][$ixApi]['operations'][$ixOps]['event_name'] = $eventNames;

                    foreach ($eventNames as $ixEventNames => $templateEventName) {
                        $eventName = str_replace(
                            [
                                '{api_name}',
                                $apiName . '.' . $apiName . '.',
                                '{action}',
                                '{request.method}'
                            ],
                            [
                                $apiName,
                                'system.' . $apiName . '.',
                                $method,
                                $method,
                            ],
                            $templateEventName
                        );

                        $eventsThrown[] = $eventName;

                        //  Set actual name in swagger file
                        $data['apis'][$ixApi]['operations'][$ixOps]['event_name'][$ixEventNames] = $eventName;

                        $eventCount++;
                    }

                    $apiBroadcastEvents[$method] = $eventsThrown;
                    $apiProcessEvents[$method] = ["$path.$method.pre_process", "$path.$method.post_process"];
                }

                unset($operation, $eventsThrown);
            }

            $processEvents[str_ireplace('{api_name}', $apiName, $path)] = $apiProcessEvents;
            $broadcastEvents[str_ireplace('{api_name}', $apiName, $path)] = $apiBroadcastEvents;

            unset($apiProcessEvents, $apiBroadcastEvents, $api);
        }

        \Log::debug('  * Discovered ' . $eventCount . ' event(s).');

        return ['process' => $processEvents, 'broadcast' => $broadcastEvents];
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @return array
     */
    public static function getEventMap()
    {
        if (!empty(static::$eventMap)) {
            return static::$eventMap;
        }

        static::$eventMap = CacheUtilities::get(static::EVENT_CACHE_KEY);

        //	If we still have no event map, build it.
        if (empty(static::$eventMap)) {
            static::buildEventMaps();
        }

        return static::$eventMap;
    }

    /**
     * Clears the cache produced by the swagger annotations
     */
    public static function clearCache()
    {
        static::$eventMap = [];
        CacheUtilities::forget(static::EVENT_CACHE_KEY);

        // rebuild swagger cache
        static::buildEventMaps();
    }
}
