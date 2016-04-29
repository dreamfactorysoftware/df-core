<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiDocFormatTypes;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Models\Service as ServiceModel;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Services\BaseFileService;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Inflector;
use ServiceManager;

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
                $serviceEvents = static::parseSwaggerEvents($service);

                //	Parse the events while we get the chance...
                $processEventMap[$apiName] = ArrayUtils::get($serviceEvents, 'process', []);
                $broadcastEventMap[$apiName] = ArrayUtils::get($serviceEvents, 'broadcast', []);

                unset($content, $service, $serviceEvents);
            } catch (\Exception $ex) {
                \Log::error("  * System error building event map for service '$apiName'.\n{$ex->getMessage()}");
            }
        }

        static::$eventMap = ['process' => $processEventMap, 'broadcast' => $broadcastEventMap];

        \Log::info('Event cache build process complete');
    }

    /**
     * @param ServiceModel $model
     *
     * @return array
     * @throws \Exception
     */
    protected static function parseSwaggerEvents(ServiceModel $model)
    {
        if (empty($content = ServiceModel::getStoredContentForService($model))) {
            throw new \Exception('  * No event content found for service.');
        }

        /** @var BaseRestService $serviceClass */
        $accessList = [];
        if ($model->is_active) {
            
            /** @var BaseRestService $service */
            $service = ServiceManager::getService($model->name);
            if ($service instanceof BaseFileService) {
                // don't want the full folder list here
                $accessList = (empty($service->getPermissions()) ? [] : ['', '*']);
            } else {
                $accessList = $service->getAccessList();
            }
        }

        $processEvents = [];
        $broadcastEvents = [];
        $eventCount = 0;

        foreach (ArrayUtils::get($content, 'paths', []) as $path => $api) {
            $apiProcessEvents = [];
            $apiBroadcastEvents = [];
            $apiParameters = [];
            $pathParameters = [];

            $eventPath = str_replace(
                ['{service.name}', '/'],
                [$model->name, '.'],
                trim($path, '/')
            );
            $resourcePath = ltrim(strstr(trim($path, '/'), '/'), '/');
            $replacePos = strpos($resourcePath, '{');

            foreach ($api as $ixOps => $operation) {
                if ('parameters' === $ixOps) {
                    $pathParameters = $operation;
                    continue;
                }

                $method = strtolower($ixOps);
                if (null !== ($eventNames = ArrayUtils::get($operation, 'x-publishedEvents'))) {
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

                    foreach ($eventNames as $ixEventNames => $templateEventName) {
                        $eventName = str_replace(
                            [
                                '{service.name}',
                                '{request.method}'
                            ],
                            [
                                $model->name,
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

                        $eventCount++;
                    }
                }

                if (!isset($apiProcessEvents[$method])) {
                    $apiProcessEvents[$method][] = "$eventPath.$method.pre_process";
                    $apiProcessEvents[$method][] = "$eventPath.$method.post_process";
                    $parameters = ArrayUtils::get($operation, 'parameters', []);
                    if (!empty($pathParameters)) {
                        $parameters = array_merge($pathParameters, $parameters);
                    }
                    foreach ($parameters as $parameter) {
                        $type = ArrayUtils::get($parameter, 'in', '');
                        if ('path' === $type) {
                            $name = ArrayUtils::get($parameter, 'name', '');
                            $options = ArrayUtils::get($parameter, 'enum', ArrayUtils::get($parameter, 'options'));
                            if (empty($options) && !empty($accessList) && (false !== $replacePos)) {
                                $checkFirstOption = strstr(substr($resourcePath, $replacePos + 1), '}', true);
                                if ($name !== $checkFirstOption) {
                                    continue;
                                }
                                $options = [];
                                // try to match any access path
                                foreach ($accessList as $access) {
                                    $access = rtrim($access, '/*');
                                    if (!empty($access) && (strlen($access) > $replacePos)) {
                                        if (0 === substr_compare($access, $resourcePath, 0, $replacePos)) {
                                            $option = substr($access, $replacePos);
                                            if (false !== strpos($option, '/')) {
                                                $option = strstr($option, '/', true);
                                            }
                                            $options[] = $option;
                                        }
                                    }
                                }
                            }
                            if (!empty($options)) {
                                $apiParameters[$name] = array_values(array_unique($options));
                            }
                        }
                    }
                }

                unset($operation);
            }

            $processEvents[$eventPath]['verb'] = $apiProcessEvents;
            $apiParameters = (empty($apiParameters)) ? null : $apiParameters;
            $processEvents[$eventPath]['parameter'] = $apiParameters;
            $broadcastEvents[$eventPath]['verb'] = $apiBroadcastEvents;

            unset($apiProcessEvents, $apiBroadcastEvents, $apiParameters, $api);
        }

        \Log::debug('  * Discovered ' . $eventCount . ' event(s).');

        return ['process' => $processEvents, 'broadcast' => $broadcastEvents];
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @param bool $refresh
     *
     * @return array
     */
    public static function getEventMap($refresh = false)
    {
        if (!empty(static::$eventMap)) {
            return static::$eventMap;
        }

        static::$eventMap = ($refresh ? [] : \Cache::get(static::EVENT_CACHE_KEY));

        //	If we still have no event map, build it.
        if (empty(static::$eventMap)) {
            static::buildEventMaps();
            //	Write event cache file
            \Cache::forever(static::EVENT_CACHE_KEY, static::$eventMap);
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
        $refresh = $this->request->getParameterAsBool('refresh');
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

            $results = $this->getEventMap($refresh);
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

        return ResponseFactory::create($foundModel->toArray());
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

    public static function getApiDocInfo(\DreamFactory\Core\Models\Service $service, array $resource = [])
    {
        $serviceName = strtolower($service->name);
        $capitalized = Inflector::camelize($service->name);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(ArrayUtils::get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $eventPath = $serviceName . '.' . $resourceName;

        $apis = [
            $path                   => [
                'get' => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . 'EventList() - Retrieve list of events.',
                    'operationId'       => 'get' . $capitalized . 'EventList',
                    'description'       => 'A list of event names are returned.<br>' .
                        'The list can be limited by service and/or by type.',
                    'x-publishedEvents' => $eventPath . '.list',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::AS_LIST),
                        [
                            'name'        => 'service',
                            'description' => 'Get the events for only this service.',
                            'type'        => 'string',
                            'in'          => 'query',
                            'required'    => false,
                        ],
                        [
                            'name'        => 'type',
                            'description' => 'Get the events for only this type - process or broadcast.',
                            'type'        => 'string',
                            'in'          => 'query',
                            'required'    => false,
                            'enum'        => ['process', 'broadcast'],
                        ],
                        [
                            'name'        => 'only_scripted',
                            'description' => 'Get only the events that have associated scripts.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'required'    => false,
                            'default'     => false,
                        ],
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Resource List',
                            'schema'      => ['$ref' => '#/definitions/ResourceList']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
            ],
            $path . '/{event_name}' => [
                'parameters' => [
                    [
                        'name'        => 'event_name',
                        'description' => 'Identifier of the event to retrieve.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                    ApiOptions::documentOption(ApiOptions::FIELDS),
                    ApiOptions::documentOption(ApiOptions::RELATED),
                ],
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . 'EventScript() - Retrieve the script for an event.',
                    'operationId'       => 'get' . $capitalized . 'EventScript',
                    'description'       =>
                        'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                        'By default, all fields and no relations are returned for each record.',
                    'x-publishedEvents' => $eventPath . '.{event_name}.read',
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FILE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Event Script',
                            'schema'      => ['$ref' => '#/definitions/EventScriptResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'post'       => [
                    'tags'              => [$serviceName],
                    'summary'           => 'create' . $capitalized . 'EventScript() - Create a script for an event.',
                    'operationId'       => 'create' . $capitalized . 'EventScript',
                    'description'       =>
                        'Post data should be a single record containing required fields for a script. ' .
                        'By default, only the event name of the record affected is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                    'x-publishedEvents' => $eventPath . '.{event_name}.create',
                    'parameters'        => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of records to create.',
                            'schema'      => ['$ref' => '#/definitions/EventScriptRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Event Script',
                            'schema'      => ['$ref' => '#/definitions/EventScriptResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'delete'     => [
                    'tags'              => [$serviceName],
                    'summary'           => 'delete' . $capitalized . 'EventScript() - Delete an event scripts.',
                    'operationId'       => 'delete' . $capitalized . 'EventScript',
                    'description'       =>
                        'By default, only the event name of the record deleted is returned on success. ' .
                        'Use \'fields\' and \'related\' to return more properties of the deleted record.',
                    'x-publishedEvents' => $eventPath . '.{event_name}.delete',
                    'parameters'        => [],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
            ],
        ];

        $models = [];

        $model = new EventScript;
        $temp = $model->toApiDocsModel('EventScript');
        if ($temp) {
            $models = array_merge($models, $temp);
        }

        return ['paths' => $apis, 'definitions' => $models];
    }
}