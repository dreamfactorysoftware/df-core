<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiDocFormatTypes;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Models\Service as ServiceModel;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;

/**
 * Class Event
 *
 * @package DreamFactory\Core\Resources
 */
class Event extends BaseRestResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string A cached process-handling events list derived from API Docs
     */
    const EVENT_CACHE_KEY = 'events';

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
        $result = ServiceModel::with(
            [
                'serviceDocs' => function ($query){
                    $query->where('format', ApiDocFormatTypes::SWAGGER);
                }
            ]
        )->get();

        //	Spin through services and pull the events
        foreach ($result as $service) {
            $apiName = $service->name;
            try {
                if (empty($content = ServiceModel::getStoredContentForService($service))) {
                    throw new \Exception('  * No event content found for service.');
                    continue;
                }

                $serviceEvents = static::parseSwaggerEvents($apiName, $content);

                //	Parse the events while we get the chance...
                $processEventMap[$apiName] = ArrayUtils::get($serviceEvents, 'process', []);
                $broadcastEventMap[$apiName] = ArrayUtils::get($serviceEvents, 'broadcast', []);

                unset($content, $service, $serviceEvents);
            } catch (\Exception $ex) {
                \Log::error("  * System error building event map for service '$apiName'.\n{$ex->getMessage()}");
            }
        }

        static::$eventMap = ['process' => $processEventMap, 'broadcast' => $broadcastEventMap];

        //	Write event cache file
        \Cache::forever(static::EVENT_CACHE_KEY, static::$eventMap);

        \Log::info('Event cache build process complete');
    }

    /**
     * @param string $apiName
     * @param array  $data
     *
     * @return array
     */
    protected static function parseSwaggerEvents($apiName, &$data)
    {
        $processEvents = [];
        $broadcastEvents = [];
        $eventCount = 0;

        foreach (ArrayUtils::get($data, 'apis', []) as $ixApi => $api) {
            $apiProcessEvents = [];
            $apiBroadcastEvents = [];
            $apiParameters = [];

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

                        if (!isset($apiBroadcastEvents[$method]) ||
                            false === array_search($eventName, $apiBroadcastEvents[$method])
                        ) {
                            // should not have duplicates here.
                            $apiBroadcastEvents[$method][] = $eventName;
                        }

                        //  Set actual name in swagger file
                        $data['apis'][$ixApi]['operations'][$ixOps]['event_name'][$ixEventNames] = $eventName;

                        $eventCount++;
                    }

                    if (!isset($apiProcessEvents[$method])) {
                        $apiProcessEvents[$method][] = "$path.$method.pre_process";
                        $apiProcessEvents[$method][] = "$path.$method.post_process";
                        $parameters = ArrayUtils::get($operation, 'parameters', []);
                        foreach ($parameters as $parameter) {
                            if (('path' === ArrayUtils::get($parameter, 'paramType')) &&
                                !empty($enums = ArrayUtils::get($parameter, 'enum'))
                            ) {
                                $name = ArrayUtils::get($parameter, 'name', '');
                                $apiParameters[$name] = $enums;
                            }
                        }
                    }
                }

                unset($operation);
            }

            $processEvents[str_ireplace('{api_name}', $apiName, $path)]['verb'] = $apiProcessEvents;
            $apiParameters = (empty($apiParameters)) ? null : $apiParameters;
            $processEvents[str_ireplace('{api_name}', $apiName, $path)]['parameter'] = $apiParameters;
            $broadcastEvents[str_ireplace('{api_name}', $apiName, $path)]['verb'] = $apiBroadcastEvents;

            unset($apiProcessEvents, $apiBroadcastEvents, $apiParameters, $api);
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

        static::$eventMap = \Cache::get(static::EVENT_CACHE_KEY);

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
        \Cache::forget(static::EVENT_CACHE_KEY);
    }

    //*************************************************************************
    //	Methods
    //*************************************************************************

    protected static function affectsProcess($event)
    {
        $sections = explode('.', $event);
        $last = $sections[count($sections) - 1];
        if ((0 === strcasecmp('pre_process', $last)) || (0 === strcasecmp('post_process', $last))) {
            return true;
        }

        return false;
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        if (empty($this->resource)) {
            $service = $this->request->getParameter('service');
            $type = $this->request->getParameter('type');
            $onlyScripted = $this->request->getParameterAsBool('only_scripted');
            if ($onlyScripted) {
                switch ($type) {
                    case 'process':
                        $scripts = EventScript::where('affects_process', 1)->lists('name')->all();
                        break;
                    case 'broadcast':
                        $scripts = EventScript::where('affects_process', 0)->lists('name')->all();
                        break;
                    default:
                        $scripts = EventScript::lists('name')->all();
                        break;
                }

                return ResourcesWrapper::cleanResources(array_values(array_unique($scripts)));
            }

            $results = $this->getEventMap();
            $allEvents = [];
            switch ($type) {
                case 'process':
                    $results = ArrayUtils::get($results, 'process', []);
                    foreach ($results as $serviceKey => $apis) {
                        if (!empty($service) && (0 !== strcasecmp($service, $serviceKey))) {
                            unset($results[$serviceKey]);
                        } else {
                            foreach ($apis as $path => $operations) {
                                foreach ($operations['verb'] as $method => $events) {
                                    $allEvents = array_merge($allEvents, $events);
                                }
                            }
                        }
                    }
                    break;
                case 'broadcast':
                    $results = ArrayUtils::get($results, 'broadcast', []);
                    foreach ($results as $serviceKey => $apis) {
                        if (!empty($service) && (0 !== strcasecmp($service, $serviceKey))) {
                            unset($results[$serviceKey]);
                        } else {
                            foreach ($apis as $path => $operations) {
                                foreach ($operations['verb'] as $method => $events) {
                                    $allEvents = array_merge($allEvents, $events);
                                }
                            }
                        }
                    }
                    break;
                default:
                    foreach ($results as $type => $services) {
                        foreach ($services as $serviceKey => $apis) {
                            if (!empty($service) && (0 !== strcasecmp($service, $serviceKey))) {
                                unset($results[$type][$serviceKey]);
                            } else {
                                foreach ($apis as $path => $operations) {
                                    foreach ($operations['verb'] as $method => $events) {
                                        $allEvents = array_merge($allEvents, $events);
                                    }
                                }
                            }
                        }
                    }
                    break;
            }

            if (!$this->request->getParameterAsBool(ApiOptions::AS_LIST)) {
                return $results;
            }

            return ResourcesWrapper::cleanResources(array_values(array_unique($allEvents)));
        }

        $related = $this->request->getParameter(ApiOptions::RELATED);
        if (!empty($related)) {
            $related = explode(',', $related);
        } else {
            $related = [];
        }

        //	Single script by name
        $fields = [ApiOptions::FIELDS_ALL];
        if (null !== ($value = $this->request->getParameter(ApiOptions::FIELDS))) {
            $fields = explode(',', $value);
        }

        if (null === $foundModel = EventScript::with($related)->find($this->resource, $fields)) {
            throw new NotFoundException("Script not found.");
        }

        return ResponseFactory::create($foundModel->toArray(), $this->nativeFormat);
    }

    /**
     * Handles POST action
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePOST()
    {
        if (empty($this->resource)) {
            return false;
        }

        $record = $this->getPayloadData();
        if (empty($record)) {
            throw new BadRequestException('No record detected in request.');
        }

        $record['affects_process'] = static::affectsProcess($this->resource);
        if (EventScript::whereName($this->resource)->exists()) {
            $result = EventScript::updateById($this->resource, $record, $this->request->getParameters());
        } else {
            $result = EventScript::createById($this->resource, $record, $this->request->getParameters());
        }

        return $result;
    }

    /**
     * Handles DELETE action
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handleDELETE()
    {
        if (empty($this->resource)) {
            return false;
        }

        return EventScript::deleteById($this->resource, $this->request->getParameters());
    }

    /**
     * @return array
     */
    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $name = Inflector::camelize($this->name);

        // use the cached copy, don't try to create it here, infinite loop!
        $results = \Cache::get(static::EVENT_CACHE_KEY, []);
        $allEvents = [];
        foreach ($results as $services) {
            foreach ($services as $apis) {
                foreach ($apis as $operations) {
                    foreach ($operations['verb'] as $events) {
                        foreach ($events as $event) {
                            $allEvents[] = $event;
                        }
                    }
                }
            }
        }

        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getEventList() - Retrieve list of events.',
                        'nickname'         => 'getEventList',
                        'notes'            => 'A list of event names are returned.<br>' .
                            'The list can be limited by service and/or by type.',
                        'type'             => 'ResourceList',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            ApiOptions::documentOption(ApiOptions::AS_LIST, true, true),
                            [
                                'name'          => 'service',
                                'description'   => 'Get the events for only this service.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'type',
                                'description'   => 'Get the events for only this type - process or broadcast.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                                'enum'          => [null, 'process', 'broadcast'],
                            ],
                            [
                                'name'          => 'only_scripted',
                                'description'   => 'Get only the events that have associated scripts.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'default'       => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'getEventMap() - Retrieve full map of events.',
                        'nickname'         => 'getEventMap',
                        'notes'            => 'This returns a service to verb to event mapping.<br>' .
                            'The list can be limited by service and/or by type.',
                        'type'             => 'EventMap',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            [
                                'name'          => 'service',
                                'description'   => 'Get the events for only this service.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'type',
                                'description'   => 'Get the events for only this type - process or broadcast.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                                'enum'          => [null, 'process', 'broadcast'],
                            ],
                            [
                                'name'          => 'only_scripted',
                                'description'   => 'Get only the events that have associated scripts.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'default'       => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                ],
                'description' => 'Operations for retrieving events.',
            ],
            [
                'path'        => $path . '/{event_name}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getEventScript() - Retrieve the script for an event.',
                        'nickname'         => 'getEventScript',
                        'notes'            =>
                            'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                            'By default, all fields and no relations are returned for each record.',
                        'type'             => 'EventScriptResponse',
                        'event_name'       => $eventPath . '.{event_name}.read',
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                                'enum'          => $allEvents,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                            ApiOptions::documentOption(ApiOptions::INCLUDE_SCHEMA),
                            ApiOptions::documentOption(ApiOptions::FILE),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'createEventScript() - Create a script for an event.',
                        'nickname'         => 'createEventScript',
                        'notes'            =>
                            'Post data should be a single record containing required fields for a script. ' .
                            'By default, only the event name of the record affected is returned on success, ' .
                            'use \'fields\' and \'related\' to return more info.',
                        'type'             => 'EventScriptResponse',
                        'event_name'       => $eventPath . '.{event_name}.create',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                                'enum'          => $allEvents,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to create.',
                                'allowMultiple' => false,
                                'type'          => 'EventScriptRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'delete' . $name . 'EventScript() - Delete an event scripts.',
                        'nickname'         => 'delete' . $name . 'EventScript',
                        'notes'            =>
                            'By default, only the event name of the record deleted is returned on success. ' .
                            'Use \'fields\' and \'related\' to return more properties of the deleted record.',
                        'type'             => 'EventScriptResponse',
                        'event_name'       => $eventPath . '.{event_name}.delete',
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                                'enum'          => $allEvents,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                ],
                'description' => 'Operations for scripts on individual events.',
            ],
        ];

        $models = [];

        $model = new EventScript;
        $temp = $model->toApiDocsModel('EventScript');
        if ($temp) {
            $models = array_merge($models, $temp);
        }

        return ['apis' => $apis, 'models' => $models];
    }
}