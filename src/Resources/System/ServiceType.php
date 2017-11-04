<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\ResourcesWrapper;
use ServiceManager;

class ServiceType extends ReadOnlySystemResource
{
    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return 'name';
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function handleGET()
    {
        if (!empty($this->resource)) {
            /** @type ServiceTypeInterface $type */
            if (null === $type = ServiceManager::getServiceType($this->resource)) {
                throw new NotFoundException("Service type '{$this->resource}' not found.");
            }

            return $type->toArray();
        }

        $resources = [];
        $group = $this->request->getParameter('group');
        $types = ServiceManager::getServiceTypes($group);
        /** @type ServiceTypeInterface $type */
        foreach ($types as $type) {
            $resources[] = $type->toArray();
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $fields = $this->request->getParameter(ApiOptions::FIELDS, ApiOptions::FIELDS_ALL);

        return ResourcesWrapper::cleanResources($resources, $asList, $idField, $fields);
    }

    protected function getApiDocSchemas()
    {
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'ServiceTypesResponse'              => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of registered service types.',
                        'items'       => [
                            '$ref' => '#/components/schemas/ServiceTypeResponse',
                        ],
                    ],
                ],
            ],
            'ServiceTypeResponse'    => [
                'type'       => 'object',
                'properties' => [
                    'name'              => [
                        'type'        => 'string',
                        'description' => 'Identifier for the service type.',
                    ],
                    'label'           => [
                        'type'        => 'string',
                        'description' => 'Displayable label for the service type.',
                    ],
                    'description'      => [
                        'type'        => 'string',
                        'description' => 'Description of the service type.',
                    ],
                    'group'       => [
                        'type'        => 'string',
                        'description' => 'Group this type belongs to.',
                    ],
                    'singleton'    => [
                        'type'        => 'boolean',
                        'description' => 'Can there only be one service of this type in the system?',
                    ],
                    'dependencies_required'    => [
                        'type'        => 'boolean',
                        'description' => 'Does this service type have any dependencies?',
                    ],
                    'subscription_required'            => [
                        'type'        => 'boolean',
                        'description' => 'Does this service type require a paid subscription to use?',
                    ],
                    'service_definition_editable' => [
                        'type'        => 'boolean',
                        'description' => 'Is the configuration of this service editable?',
                    ],
                    'config_schema'      => [
                        'type'        => 'array',
                        'description' => 'Configuration options for this service type.',
                        'items'       => [
                            '$ref' => '#/components/schemas/ServiceConfigSchema',
                        ],
                    ],
                ],
            ],
            'ServiceConfigSchema' => [
                'type'       => 'object',
                'properties' => [
                    'alias'                      => [
                        'type'        => 'string',
                        'description' => 'Optional alias of the option.',
                    ],
                    'name'                    => [
                        'type'        => 'string',
                        'description' => 'Name of the option.',
                    ],
                    'label'                    => [
                        'type'        => 'string',
                        'description' => 'Displayed name of the option.',
                    ],
                    'description'             => [
                        'type'        => 'string',
                        'description' => 'Description of the option.',
                    ],
                    'type'         => [
                        'type'        => 'string',
                        'description' => 'Data type of the option for storage.',
                    ],
                ],
            ],
        ];
    }
}