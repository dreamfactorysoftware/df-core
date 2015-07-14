<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\ApiDocManager;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Inflector;

/**
 * Class Event
 *
 * @package DreamFactory\Core\Resources
 */
class Event extends BaseRestResource
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return mixed
     */
    protected static function getEventMap()
    {
        return ApiDocManager::getEventMap();
    }

    protected static function affectsProcess($event)
    {
        $sections = explode('.', $event);
        $service = $sections[0];
        $results = static::getEventMap();
        foreach ($results as $type => $services) {
            foreach ($services as $serviceKey => $apis) {
                if ((0 === strcasecmp($service, $serviceKey))) {
                    foreach ($apis as $path => &$operations) {
                        foreach ($operations as $method => &$events) {
                            foreach ($events as $eventKey) {
                                if ((0 === strcasecmp($event, $eventKey))) {
                                    switch ($type) {
                                        case 'process':
                                            return true;
                                        case 'broadcast':
                                            return false;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        throw new BadRequestException("$event is not a valid system event.");
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $results = $this->getEventMap();

        if (empty($this->resource)) {
            $allEvents = [];
            $service = $this->request->getParameter('service');
            $type = $this->request->getParameter('type');
            switch ($type) {
                case 'process':
                    $scripts = EventScript::where('affects_process', 1)->lists('name');
                    $results = ArrayUtils::get($results, 'process', []);
                    foreach ($results as $serviceKey => &$apis) {
                        if (empty($service) || (0 === strcasecmp($service, $serviceKey))) {
                            foreach ($apis as $path => &$operations) {
                                foreach ($operations as $method => &$events) {
                                    $temp = [];
                                    foreach ($events as $event) {
                                        $temp[$event] = boolval(array_keys($scripts, $event));
                                        $allEvents[] = $event;
                                    }
                                    $events = $temp;
                                }
                            }
                        }
                    }
                    break;
                case 'broadcast':
                    $scripts = EventScript::where('affects_process', 0)->lists('name');
                    $results = ArrayUtils::get($results, 'broadcast', []);
                    foreach ($results as $serviceKey => &$apis) {
                        if (empty($service) || (0 === strcasecmp($service, $serviceKey))) {
                            foreach ($apis as $path => &$operations) {
                                foreach ($operations as $method => &$events) {
                                    $temp = [];
                                    foreach ($events as $event) {
                                        $temp[$event] = boolval(array_keys($scripts, $event));
                                        $allEvents[] = $event;
                                    }
                                    $events = $temp;
                                }
                            }
                        }
                    }
                    break;
                default:
                    $scripts = EventScript::lists('name');
                    foreach ($results as $type => &$services) {
                        foreach ($services as $serviceKey => &$apis) {
                            if (empty($service) || (0 === strcasecmp($service, $serviceKey))) {
                                foreach ($apis as $path => &$operations) {
                                    foreach ($operations as $method => &$events) {
                                        $temp = [];
                                        foreach ($events as $event) {
                                            $temp[$event] = boolval(array_keys($scripts, $event));
                                            $allEvents[] = $event;
                                        }
                                        $events = $temp;
                                    }
                                }
                            }
                        }
                    }
                    break;
            }

            if ($this->request->getParameterAsBool('full_map', false)) {
                return $results;
            }

            return $this->cleanResources($allEvents);
        }

        $data = null;

        $related = $this->request->getParameter('related');
        if (!empty($related)) {
            $related = explode(',', $related);
        } else {
            $related = [];
        }

        //	Single script by name
        $fields = ['*'];
        if (null !== ($value = $this->request->getParameter('fields'))) {
            $fields = explode(',', $value);
        }

        $foundModel = EventScript::with($related)->find($this->resource, $fields);
        if ($foundModel) {
            $data = $foundModel->toArray();
        }

        if (null === $data) {
            throw new NotFoundException("Record not found.");
        }

        return ResponseFactory::create($data, $this->nativeFormat);
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

        $result = EventScript::deleteById($this->resource, $this->request->getParameters());

        return $result;
    }

    /**
     * @return array
     */
    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $name = Inflector::camelize($this->name);
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . 'Events() - Retrieve list of events.',
                        'nickname'         => 'get' . $name . 'Events',
                        'type'             => 'ComponentList',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'A list of event names are returned. <br>',
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . 'EventMap() - Retrieve full map of events.',
                        'nickname'         => 'get' . $name . 'EventMap',
                        'type'             => 'EventMap',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            [
                                'name'          => 'full_map',
                                'description'   => 'Get the full mapping of events.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => true,
                                'default'       => true,
                            ]
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'This returns a service to verb to event mapping. <br>',
                    ],
                ],
                'description' => 'Operations for retrieving events.',
            ],
            [
                'path'        => $path . '/{event_name}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . 'EventScript() - Retrieve the script for an event.',
                        'nickname'         => 'get' . $name . 'EventScript',
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
                            ],
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to retrieve for each record.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to retrieve for each record.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'include_schema',
                                'description'   => 'Include the schema of the table queried in returned metadata.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'file',
                                'description'   => 'Download the contents of the script as a file.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            =>
                            'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                            'By default, all fields and no relations are returned for each record.',
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'create' . $name . 'EventScript() - Create a script for an event.',
                        'nickname'         => 'create' . $name . 'EventScript',
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
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to create.',
                                'allowMultiple' => false,
                                'type'          => 'EventScriptRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ]
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            =>
                            'Post data should be a single record containing required fields for a script. ' .
                            'By default, only the id property of the record affected is returned on success, ' .
                            'use \'fields\' and \'related\' to return more info.',
                    ],
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'update' . $name . 'EventScript() - Update a script for an event.',
                        'nickname'         => 'update' . $name . 'EventScript',
                        'type'             => 'EventScriptResponse',
                        'event_name'       => $eventPath . '.{event_name}.update',
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
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to update.',
                                'allowMultiple' => false,
                                'type'          => 'EventScriptRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            =>
                            'Posted data should be a single record containing changed fields. ' .
                            'By default, only the id property of the record is returned on success, ' .
                            'use \'fields\' and \'related\' to return more info.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'delete' . $name . 'EventScript() - Delete an event scripts.',
                        'nickname'         => 'delete' . $name . 'EventScript',
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
                            ],
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            =>
                            'By default, only the id property of the record deleted is returned on success. ' .
                            'Use \'fields\' and \'related\' to return more properties of the deleted record.',
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