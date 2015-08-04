<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Utility\ApiDocUtilities;
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
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $config = [];

        $config['apis'] = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getConfig() - Retrieve system configuration properties.',
                        'nickname'         => 'getConfig',
                        'type'             => 'ConfigResponse',
                        'event_name'       => $eventPath . '.read',
                        'notes'            => 'The retrieved properties control how the system behaves.',
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'setConfig() - Update one or more system configuration properties.',
                        'nickname'         => 'setConfig',
                        'type'             => 'ConfigResponse',
                        'event_name'       => $eventPath . '.update',
                        'notes'            => 'Post data should be an array of properties.',
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of properties to set.',
                                'allowMultiple' => false,
                                'type'          => 'ConfigRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                    ],
                ],
                'description' => 'Operations for system configuration options.',
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

        $config['models'] = [
            'ConfigRequest'  => [
                'id'         => 'ConfigRequest',
                'properties' => $commonProperties,
            ],
            'ConfigResponse' => [
                'id'         => 'ConfigResponse',
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