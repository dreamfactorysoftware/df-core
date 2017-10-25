<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceResponse;
use DreamFactory\Core\Utility\Session;
use ServiceManager;
use Log;

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
            if (Session::allowsServiceAccess($serviceName)) {
                try {
                    if (!empty($service = ServiceManager::getService($serviceName))) {
                        if (!empty($map = $service->getEventMap())) {
                            $eventMap[$serviceName] = $map;
                        }
                    }
                } catch (\Exception $ex) {
                    Log::info("Failed to build event map for service $serviceName. {$ex->getMessage()}");
                }
            }
        } else {
            foreach (ServiceManager::getServiceNames() as $serviceName) {
                if (Session::allowsServiceAccess($serviceName)) {
                    try {
                        if (!empty($service = ServiceManager::getService($serviceName))) {
                            if (!empty($map = $service->getEventMap())) {
                                $eventMap[$serviceName] = $map;
                            }
                        }
                    } catch (\Exception $ex) {
                        Log::info("Failed to build event map for service $serviceName. {$ex->getMessage()}");
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

    protected function getApiDocPaths()
    {
        $service = $this->getServiceName();
        $capitalized = camelize($service);
        $resourceName = strtolower($this->name);

        return [
            '/' . $resourceName => [
                'get' => [
                    'summary'     => 'get' . $capitalized . 'EventList() - Retrieve list of events.',
                    'operationId' => 'get' . $capitalized . 'EventList',
                    'description' => 'A list of event names are returned. The list can be limited by service.',
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::AS_LIST),
                        [
                            'name'        => 'service',
                            'description' => 'Get the events for only this service.',
                            'schema'      => ['type' => 'string'],
                            'in'          => 'query',
                        ],
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/ResourceList']
                    ],
                ],
            ],
        ];
    }
}