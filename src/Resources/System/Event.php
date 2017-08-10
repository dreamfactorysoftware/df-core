<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceResponse;
use DreamFactory\Core\Utility\Session;
use ServiceManager;

/**
 * Class Event
 *
 * @package DreamFactory\Core\Resources
 */
class Event extends BaseRestResource
{
    /**
     * Handles GET action
     *
     * @return array|ServiceResponse
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $scriptable = $this->request->getParameterAsBool('scriptable');

        \Log::info('Building event map');

        $eventMap = [];
        if (!empty($serviceName = $this->request->getParameter('service'))) {
            if (Session::checkForAnyServicePermissions($serviceName)) {
                if (!empty($service = ServiceManager::getService($serviceName))) {
                    if (!empty($map = $service->getEventMap())) {
                        $eventMap[$serviceName] = $map;
                    }
                }
            }
        } else {
            foreach (ServiceManager::getServiceNames() as $serviceName) {
                if (Session::checkForAnyServicePermissions($serviceName)) {
                    if (!empty($service = ServiceManager::getService($serviceName))) {
                        if (!empty($map = $service->getEventMap())) {
                            $eventMap[$serviceName] = $map;
                        }
                    }
                }
            }
        }

        \Log::info('Event map build process complete');

        $allEvents = [];
        foreach ($eventMap as $serviceKey => $paths) {
            foreach ($paths as $path => $operations) {
                if (empty($type = array_get($operations, 'type'))) {
                    $type = 'service';
                    $eventMap[$serviceKey][$path]['type'] = $type;
                }
                if (!empty($endpoints = (array)array_get($operations, 'endpoints', $path))) {
                    if ($scriptable) {
                        $temp = [];
                        foreach ($endpoints as $endpoint) {
                            $temp[] = $endpoint;
                            switch ($type) {
                                case 'api':
                                    // add pre_process, post_process
                                    $temp[] = "$endpoint.pre_process";
                                    $temp[] = "$endpoint.post_process";
                                    break;
                                case 'service':
                                    break;
                            }
                            // add queued
                            $temp[] = "$endpoint.queued";
                        }
                        $endpoints = $temp;
                        $eventMap[$serviceKey][$path]['endpoints'] = $temp;
                    }
                    $allEvents = array_merge($allEvents, $endpoints);
                }
            }
        }

        if (!$this->request->getParameterAsBool(ApiOptions::AS_LIST)) {
            return $eventMap;
        }

        return ResourcesWrapper::cleanResources(array_values(array_unique($allEvents)));
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = camelize($service);
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