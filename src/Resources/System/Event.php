<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service as ServiceModel;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Contracts\FileServiceInterface;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceResponse;
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
     */
    protected static function buildEventMaps()
    {
        \Log::info('Building event cache');

        //  Build event mapping from services in database

        //	Initialize the event map
        $eventMap = [];

        //  Pull any custom swagger docs
        $result = ServiceModel::whereIsActive(true)->get();

        //	Spin through services and pull the events
        /** @var ServiceModel $model */
        foreach ($result as $model) {
            $apiName = $model->name;
            try {
                /** @var BaseRestService $service */
                if (empty($service = ServiceManager::getService($apiName))) {
                    throw new \Exception('No service found.');
                }

                if ($service instanceof FileServiceInterface) {
                    // don't want the full folder list here
                    $accessList = (empty($service->getPermissions()) ? [] : ['', '*']);
                } else {
                    $accessList = $service->getAccessList();
                }

                if (!empty($accessList)) {
                    if (!empty($doc = $model->getDocAttribute())) {
                        if (is_array($doc) && !empty($content = array_get($doc, 'content'))) {
                            if (is_string($content)) {
                                $content = ServiceModel::storedContentToArray($content, array_get($doc, 'format'),
                                    $model);
                                if (!empty($content)) {
                                    $eventMap[$apiName] = static::parseSwaggerEvents($content, $accessList);
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $ex) {
                \Log::error("  * System error building event map for service '$apiName'.\n{$ex->getMessage()}");
            }

            unset($content, $service, $serviceEvents);
        }

        static::$eventMap = $eventMap;

        \Log::info('Event cache build process complete');
    }

    /**
     * @param array $content
     * @param array $access
     *
     * @return array
     */
    protected static function parseSwaggerEvents(array $content, array $access = [])
    {
        $events = [];
        $eventCount = 0;

        foreach (array_get($content, 'paths', []) as $path => $api) {
            $apiEvents = [];
            $apiParameters = [];
            $pathParameters = [];

            $eventPath = str_replace('/', '.', trim($path, '/'));
            $resourcePath = ltrim(strstr(trim($path, '/'), '/'), '/');
            $replacePos = strpos($resourcePath, '{');

            foreach ($api as $ixOps => $operation) {
                if ('parameters' === $ixOps) {
                    $pathParameters = $operation;
                    continue;
                }

                $method = strtolower($ixOps);
                if (!isset($apiEvents[$method])) {
                    $apiEvents[$method][] = "$eventPath.$method";
                    $parameters = array_get($operation, 'parameters', []);
                    if (!empty($pathParameters)) {
                        $parameters = array_merge($pathParameters, $parameters);
                    }
                    foreach ($parameters as $parameter) {
                        $type = array_get($parameter, 'in', '');
                        if ('path' === $type) {
                            $name = array_get($parameter, 'name', '');
                            $options = array_get($parameter, 'enum', array_get($parameter, 'options'));
                            if (empty($options) && !empty($access) && (false !== $replacePos)) {
                                $checkFirstOption = strstr(substr($resourcePath, $replacePos + 1), '}', true);
                                if ($name !== $checkFirstOption) {
                                    continue;
                                }
                                $options = [];
                                // try to match any access path
                                foreach ($access as $accessPath) {
                                    $accessPath = rtrim($accessPath, '/*');
                                    if (!empty($accessPath) && (strlen($accessPath) > $replacePos)) {
                                        if (0 === substr_compare($accessPath, $resourcePath, 0, $replacePos)) {
                                            $option = substr($accessPath, $replacePos);
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

            $events[$eventPath]['verb'] = $apiEvents;
            $apiParameters = (empty($apiParameters)) ? null : $apiParameters;
            $events[$eventPath]['parameter'] = $apiParameters;

            unset($apiEvents, $apiParameters, $api);
        }

        \Log::debug('  * Discovered ' . $eventCount . ' event(s).');

        return $events;
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

    /**
     * Handles GET action
     *
     * @return array|ServiceResponse
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $refresh = $this->request->getParameterAsBool('refresh');
        $scriptable = $this->request->getParameterAsBool('scriptable');
        $service = $this->request->getParameter('service');
        $results = $this->getEventMap($refresh);
        $allEvents = [];
        foreach ($results as $serviceKey => $paths) {
            if (!empty($service) && (0 !== strcasecmp($service, $serviceKey))) {
                unset($results[$serviceKey]);
            } else {
                foreach ($paths as $path => $operations) {
                    foreach ($operations['verb'] as $method => $events) {
                        if ($scriptable) {
                            foreach ($events as $ndx => $event) {
                                $temp = [
                                    $event . '.pre_process',
                                    $event . '.post_process',
                                    $event . '.queued',
                                ];
                                $results[$serviceKey][$path]['verb'][$method] = $temp;
                                $allEvents = array_merge($allEvents, $temp);
                            }
                        } else {
                            $allEvents = array_merge($allEvents, $events);
                        }
                    }
                }
            }
        }

        if (!$this->request->getParameterAsBool(ApiOptions::AS_LIST)) {
            return $results;
        }

        return ResourcesWrapper::cleanResources(array_values(array_unique($allEvents)));
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;

        $paths = [
            $path => [
                'get' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'get' . $capitalized . 'EventList() - Retrieve list of events.',
                    'operationId' => 'get' . $capitalized . 'EventList',
                    'description' => 'A list of event names are returned. The list can be limited by service.',
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::AS_LIST),
                        [
                            'name'        => 'service',
                            'description' => 'Get the events for only this service.',
                            'type'        => 'string',
                            'in'          => 'query',
                            'required'    => false,
                        ],
                    ],
                    'responses'   => [
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
        ];

        return ['paths' => $paths, 'definitions' => []];
    }
}