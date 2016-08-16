<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\EventScript as EventScriptModel;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\ServiceResponse;
use DreamFactory\Library\Utility\Inflector;

/**
 * Class Event
 *
 * @package DreamFactory\Core\Resources
 */
class EventScript extends BaseRestResource
{
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
        if (empty($this->resource)) {
            $scripts = EventScriptModel::pluck('name')->all();

            return ResourcesWrapper::cleanResources($scripts);
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

        if (null === $foundModel = EventScriptModel::with($related)->find($this->resource, $fields)) {
            throw new NotFoundException("Script not found.");
        }

        return ResponseFactory::create($foundModel->toArray());
    }

    /**
     * Handles POST action
     *
     * @return bool|ServiceResponse
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

        if (EventScriptModel::whereName($this->resource)->exists()) {
            $result = EventScriptModel::updateById($this->resource, $record, $this->request->getParameters());
        } else {
            $result = EventScriptModel::createById($this->resource, $record, $this->request->getParameters());
        }

        return $result;
    }

    /**
     * Handles POST action
     *
     * @return bool|ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePUT()
    {
        if (empty($this->resource)) {
            return false;
        }

        $record = $this->getPayloadData();
        if (empty($record)) {
            throw new BadRequestException('No record detected in request.');
        }

        if (EventScriptModel::whereName($this->resource)->exists()) {
            $result = EventScriptModel::updateById($this->resource, $record, $this->request->getParameters());
        } else {
            $result = EventScriptModel::createById($this->resource, $record, $this->request->getParameters());
        }

        return $result;
    }

    /**
     * Handles DELETE action
     *
     * @return bool|ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handleDELETE()
    {
        if (empty($this->resource)) {
            return false;
        }

        return EventScriptModel::deleteById($this->resource, $this->request->getParameters());
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $eventPath = $serviceName . '.' . $resourceName;

        $paths = [
            $path                   => [
                'get' => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . 'EventScripts() - Retrieve list of event scripts.',
                    'operationId'       => 'get' . $capitalized . 'EventScripts',
                    'description'       => 'A list of event scripts.',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::AS_LIST),
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
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
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
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
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
                'put'       => [
                    'tags'              => [$serviceName],
                    'summary'           => 'update' . $capitalized . 'EventScript() - Update a script for an event.',
                    'operationId'       => 'update' . $capitalized . 'EventScript',
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
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
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
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

        $model = new EventScriptModel;
        $temp = $model->toApiDocsModel('EventScript');
        if ($temp) {
            $models = array_merge($models, $temp);
        }

        return ['paths' => $paths, 'definitions' => $models];
    }
}