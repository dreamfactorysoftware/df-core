<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;

class Config extends BaseSystemResource
{

    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::POST,
            Verbs::MERGE => Verbs::POST,
            Verbs::PATCH => Verbs::POST
        ];
        ArrayUtils::set($settings, "verbAliases", $verbAliases);

        parent::__construct($settings);

        $this->model = \DreamFactory\Core\Models\Config::class;
    }

    public function getApiDocInfo()
    {
        $serviceName = $this->getServiceName();
        $path = '/' . $serviceName . '/' . $this->getFullPathName();
        $eventPath = $serviceName . '.' . $this->getFullPathName('.');
        $config = [];

        $config['paths'] = [
            $path => [
                'get'  => [
                    'tags'        => [$serviceName],
                    'summary'     => 'getConfig() - Retrieve system configuration properties.',
                    'operationId' => 'getConfig',
                    'event_name'  => $eventPath . '.read',
                    'description' => 'The retrieved properties control how the system behaves.',
                    'responses'   => [
                        '200'     => [
                            'description' => 'Config',
                            'schema'      => ['$ref' => '#/definitions/ConfigResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'post' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'setConfig() - Update one or more system configuration properties.',
                    'operationId' => 'setConfig',
                    'event_name'  => $eventPath . '.update',
                    'description' => 'Post data should be an array of properties.',
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of properties to set.',
                            'schema'      => ['$ref' => '#/definitions/ConfigRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
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

        $commonProperties = [
            'editable_profile_fields' => [
                'type'        => 'string',
                'description' => 'Comma-delimited list of fields the user is allowed to edit.',
            ],
            'restricted_verbs'        => [
                'type'        => 'array',
                'description' => 'An array of HTTP verbs that must be tunnelled on this server.',
                'items'       => [
                    'type' => 'string',
                ],
            ],
            'timestamp_format'        => [
                'type'        => 'string',
                'description' => 'The date/time format used for timestamps.',
            ],
        ];

        $config['definitions'] = [
            'ConfigRequest'  => [
                'type'       => 'object',
                'properties' => $commonProperties,
            ],
            'ConfigResponse' => [
                'type'       => 'object',
                'properties' => $commonProperties,
            ],
        ];

        return $config;
    }

    protected function handlePOST()
    {
        if (!empty($this->resource)) {
            throw new BadRequestException('Create record by identifier not currently supported.');
        }

        $records = $this->getPayloadData();

        if (empty($records)) {
            throw new BadRequestException('No record detected in request.');
        }

        $this->triggerActionEvent($this->response);

        /** @type BaseSystemModel $modelClass */
        $modelClass = $this->model;

        return $modelClass::create($records);
    }
}