<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\BaseRestResource;

/**
 * Class Event
 *
 * @package DreamFactory\Core\Resources
 */
class Event extends BaseRestResource
{
    /**
     * @var array
     */
    protected $resources = [
        EventScript::RESOURCE_NAME    => [
            'name'       => EventScript::RESOURCE_NAME,
            'class_name' => EventScript::class,
            'label'      => 'Scripts',
        ],
        ProcessEvent::RESOURCE_NAME   => [
            'name'       => ProcessEvent::RESOURCE_NAME,
            'class_name' => ProcessEvent::class,
            'label'      => 'Process Events',
        ],
        BroadcastEvent::RESOURCE_NAME => [
            'name'       => BroadcastEvent::RESOURCE_NAME,
            'class_name' => BroadcastEvent::class,
            'label'      => 'Broadcast Events',
        ],
    ];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return array
     */
    protected function getResources()
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $base = parent::getApiDocInfo();

        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'     => 'GET',
                        'summary'    => 'getEventResources() - Retrieve event resources.',
                        'nickname'   => 'getEventResources',
                        'type'       => 'ComponentList',
                        'event_name' => [],
                        'notes'      => 'The retrieved information describes the resources available for this service.',
                    ],
                ],
                'description' => 'Operations for event service options.',
            ]
        ];

        $models = [];
        foreach ($this->resources as $resourceInfo) {
            $className = $resourceInfo['class_name'];

            if (!class_exists($className)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $this->resourcePath);
            }

            /** @var BaseRestResource $resource */
            $resource = $this->instantiateResource($className, $resourceInfo);

            $name = $className::RESOURCE_NAME . '/';
            $_access = $this->getPermissions($name);
            if (!empty($_access)) {
                $results = $resource->getApiDocInfo();
                if (isset($results, $results['apis'])) {
                    $apis = array_merge($apis, $results['apis']);
                }
                if (isset($results, $results['models'])) {
                    $models = array_merge($models, $results['models']);
                }
            }
        }

        $base['apis'] = array_merge($base['apis'], $apis);
        $base['models'] = array_merge($base['models'], $models);

        return $base;
    }
}