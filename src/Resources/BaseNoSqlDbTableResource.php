<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Enums\ApiOptions;

abstract class BaseNoSqlDbTableResource extends BaseDbTableResource
{
    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $eventPath = $serviceName . '.' . $resourceName;
        $base = parent::getApiDocInfo($service, $resource);

        $wrapper = ResourcesWrapper::getWrapper();

        $apis = [
            $path . '/{table_name}'      => [
                'parameters' => [
                    [
                        'name'        => 'table_name',
                        'description' => 'Name of the table to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . 'Records() - Retrieve one or more records.',
                    'operationId'       => 'get' . $capitalized . 'Records',
                    'description'       =>
                        'Set the <b>filter</b> parameter to a SQL WHERE clause (optional native filter accepted in some scenarios) ' .
                        'to limit records returned or leave it blank to return all records up to the maximum limit.<br/> ' .
                        'Set the <b>limit</b> parameter with or without a filter to return a specific amount of records.<br/> ' .
                        'Use the <b>offset</b> parameter along with the <b>limit</b> parameter to page through sets of records.<br/> ' .
                        'Set the <b>order</b> parameter to SQL ORDER_BY clause containing field and optional direction (<field_name> [ASC|DESC]) to order the returned records.<br/> ' .
                        'Alternatively, to send the <b>filter</b> with or without <b>params</b> as posted data, ' .
                        'use the getRecordsByPost() POST request and post a filter with or without params.<br/>' .
                        'Pass the identifying field values as a comma-separated list in the <b>ids</b> parameter.<br/> ' .
                        'Use the <b>id_field</b> and <b>id_type</b> parameters to override or specify detail for identifying fields where applicable.<br/> ' .
                        'Alternatively, to send the <b>ids</b> as posted data, use the getRecordsByPost() POST request.<br/> ' .
                        'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                        'By default, all fields are returned for all records. ',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.select',
                        $eventPath . '.table_selected',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                        ApiOptions::documentOption(ApiOptions::LIMIT),
                        ApiOptions::documentOption(ApiOptions::OFFSET),
                        ApiOptions::documentOption(ApiOptions::ORDER),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_COUNT),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_SCHEMA),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                        ApiOptions::documentOption(ApiOptions::CONTINUES),
                        ApiOptions::documentOption(ApiOptions::ROLLBACK),
                        ApiOptions::documentOption(ApiOptions::FILE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'post'       => [
                    'tags'              => [$serviceName],
                    'summary'           => 'create' . $capitalized . 'Records() - Create one or more records.',
                    'operationId'       => 'create' . $capitalized . 'Records',
                    'description'       =>
                        'Posted data should be an array of records wrapped in a <b>record</b> element.<br/> ' .
                        'By default, only the id property of the record is returned on success. ' .
                        'Use <b>fields</b> parameter to return more info.',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.insert',
                        $eventPath . '.table_inserted',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'Data containing name-value pairs of records to create.',
                                'in'          => 'body',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'required'    => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            [
                                'name'        => 'X-HTTP-METHOD',
                                'description' => 'Override request using POST to tunnel other http request, such as DELETE or GET passing a payload.',
                                'enum'        => ['GET', 'PUT', 'PATCH', 'DELETE'],
                                'type'        => 'string',
                                'in'          => 'header',
                            ],
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'put'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'replace' .
                        $capitalized .
                        'Records() - Update (replace) one or more records.',
                    'operationId'       => 'replace' . $capitalized . 'Records',
                    'description'       =>
                        'Post data should be an array of records wrapped in a <b>' .
                        $wrapper .
                        '</b> tag.<br/> ' .
                        'If ids or filter is used, posted body should be a single record with name-value pairs ' .
                        'to update, wrapped in a <b>' .
                        $wrapper .
                        '</b> tag.<br/> ' .
                        'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                        'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                        'By default, only the id property of the record is returned on success. ' .
                        'Use <b>fields</b> parameter to return more info.',
                    'x-publishedEvents' => [$eventPath . '.{table_name}.update', $eventPath . '.table_updated',],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'Data containing name-value pairs of records to update.',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'in'          => 'body',
                                'required'    => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            ApiOptions::documentOption(ApiOptions::FILTER),
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'patch'      => [
                    'tags'              => [$serviceName],
                    'summary'           => 'update' . $capitalized . 'Records() - Update (patch) one or more records.',
                    'operationId'       => 'update' . $capitalized . 'Records',
                    'description'       =>
                        'Post data should be an array of records containing at least the identifying fields for each record.<br/> ' .
                        'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                        'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                        'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                        'By default, only the id property of the record is returned on success. ' .
                        'Use <b>fields</b> parameter to return more info.',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.update',
                        $eventPath . '.table_updated',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'A single record containing name-value pairs of fields to update.',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'in'          => 'body',
                                'required'    => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            ApiOptions::documentOption(ApiOptions::FILTER),
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'delete'     => [
                    'tags'              => [$serviceName],
                    'summary'           => 'delete' . $capitalized . 'Records() - Delete one or more records.',
                    'operationId'       => 'delete' . $capitalized . 'Records',
                    'description'       =>
                        'Set the <b>ids</b> parameter to a list of record identifying (primary key) values to delete specific records.<br/> ' .
                        'Alternatively, to delete records by a large list of ids, pass the ids in the <b>body</b>.<br/> ' .
                        'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ' .
                        'Set the <b>filter</b> parameter to a SQL WHERE clause to delete specific records, ' .
                        'otherwise set <b>force</b> to true to clear the table.<br/> ' .
                        'Alternatively, to delete by a complicated filter or to use parameter replacement, pass the filter with or without params as the <b>body</b>.<br/> ' .
                        'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ' .
                        'Set the <b>body</b> to an array of records, minimally including the identifying fields, to delete specific records.<br/> ' .
                        'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.delete',
                        $eventPath . '.table_deleted',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        =>
                        [
                            [
                                'name'        => 'body',
                                'description' => 'Data containing ids of records to delete.',
                                'schema'      => ['$ref' => '#/definitions/RecordsRequest'],
                                'in'          => 'body',
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::CONTINUES),
                            ApiOptions::documentOption(ApiOptions::ROLLBACK),
                            ApiOptions::documentOption(ApiOptions::FILTER),
                            ApiOptions::documentOption(ApiOptions::FORCE),
                        ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Records',
                            'schema'      => ['$ref' => '#/definitions/RecordsResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
            ],
            $path . '/{table_name}/{id}' => [
                'parameters' => [
                    [
                        'name'        => 'id',
                        'description' => 'Identifier of the record to retrieve.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                    [
                        'name'        => 'table_name',
                        'description' => 'Name of the table to perform operations on.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . 'Record() - Retrieve one record by identifier.',
                    'operationId'       => 'get' . $capitalized . 'Record',
                    'description'       =>
                        'Use the <b>fields</b> parameter to limit properties that are returned. ' .
                        'By default, all fields are returned.',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.{id}.select',
                        $eventPath . '.record_selected',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'put'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'replace' .
                        $capitalized .
                        'Record() - Replace the content of one record by identifier.',
                    'operationId'       => 'replace' . $capitalized . 'Record',
                    'description'       =>
                        'Post data should be an array of fields for a single record.<br/> ' .
                        'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.{id}.update',
                        $eventPath . '.record_updated',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of the replacement record.',
                            'schema'      => ['$ref' => '#/definitions/RecordRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'patch'      => [
                    'tags'              => [$serviceName],
                    'summary'           => 'update' .
                        $capitalized .
                        'Record() - Update (patch) one record by identifier.',
                    'operationId'       => 'update' . $capitalized . 'Record',
                    'description'       =>
                        'Post data should be an array of fields for a single record.<br/> ' .
                        'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.{id}.update',
                        $eventPath . '.record_updated',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of the fields to update.',
                            'schema'      => ['$ref' => '#/definitions/RecordRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
                'delete'     => [
                    'tags'              => [$serviceName],
                    'summary'           => 'delete' . $capitalized . 'Record() - Delete one record by identifier.',
                    'operationId'       => 'delete' . $capitalized . 'Record',
                    'description'       => 'Use the <b>fields</b> parameter to return more deleted properties. By default, the id is returned.',
                    'x-publishedEvents' => [
                        $eventPath . '.{table_name}.{id}.delete',
                        $eventPath . '.record_deleted',
                    ],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Record',
                            'schema'      => ['$ref' => '#/definitions/RecordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                ],
            ],
        ];

        $base['paths'] = array_merge($base['paths'], $apis);
        $base['definitions'] = array_merge($base['definitions'], static::getApiDocModels());

        return $base;
    }
}