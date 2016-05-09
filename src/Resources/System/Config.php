<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;

class Config extends BaseSystemResource
{
    protected static $model = \DreamFactory\Core\Models\Config::class;

    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::POST,
            Verbs::MERGE => Verbs::POST,
            Verbs::PATCH => Verbs::POST
        ];
        ArrayUtils::set($settings, "verbAliases", $verbAliases);

        parent::__construct($settings);
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(ArrayUtils::get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $eventPath = $serviceName . '.' . $resourceName;
        $config = [];

        $config['paths'] = [
            $path => [
                'get'  => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' .
                        $capitalized .
                        'Config() - Retrieve system configuration properties.',
                    'operationId'       => 'get' . $capitalized . 'Config',
                    'x-publishedEvents' => $eventPath . '.read',
                    'description'       => 'The retrieved properties control how the system behaves.',
                    'responses'         => [
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
                    'tags'              => [$serviceName],
                    'summary'           => 'set' .
                        $capitalized .
                        'Config() - Update one or more system configuration properties.',
                    'operationId'       => 'set' . $capitalized . 'Config',
                    'x-publishedEvents' => $eventPath . '.update',
                    'description'       => 'Post data should be an array of properties.',
                    'parameters'        => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of properties to set.',
                            'schema'      => ['$ref' => '#/definitions/ConfigRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
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

        /** @type BaseSystemModel $modelClass */
        $modelClass = static::$model;

        return $modelClass::create($records);
    }
}