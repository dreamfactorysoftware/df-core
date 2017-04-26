<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\DbRequestCriteria;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Enums\Verbs;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class BaseSystemResource
 *
 * @package DreamFactory\Core\Resources
 */
class BaseSystemResource extends BaseRestResource
{
    use DbRequestCriteria;
    /**
     * Default maximum records returned on filter request
     */
    const MAX_RECORDS_RETURNED = 1000;

    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = null;

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $settings = (array)$settings;
        $settings['verbAliases'] = [
            Verbs::PUT => Verbs::PATCH,
        ];

        parent::__construct($settings);
    }

    protected static function getResourceIdentifier()
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        if ($modelClass) {
            return $modelClass::getPrimaryKeyStatic();
        }

        throw new BadRequestException('No known identifier for resources.');
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        /** @type BaseSystemModel $modelClass */
        $modelClass = static::$model;

        $options = $this->request->getParameters();
        $criteria = $this->getSelectionCriteria();

        if (!empty($this->resource)) {
            //	Single resource by ID
            $fields = array_get($criteria, 'select');
            if (empty($data = $modelClass::selectById($this->resource, $options, $fields))) {
                throw new NotFoundException("Record with identifier '{$this->resource}' not found.");
            }

            return $data;
        }

        $meta = [];
        if (!empty($ids = array_get($options, ApiOptions::IDS))) {
            //	Multiple resources by ID
            $result = $modelClass::selectByIds($ids, $options, $criteria);
        } elseif (!empty($records = ResourcesWrapper::unwrapResources($this->getPayloadData()))) {
            //  Multiple resources by passing records to have them updated with new or more values, id field required
            $pk = $modelClass::getPrimaryKeyStatic();
            $ids = array_column($records, $pk);
            $result = $modelClass::selectByIds($ids, $options, $criteria);
        } else {
            $result = $modelClass::selectByRequest($criteria, $options);

            if ($this->request->getParameterAsBool(ApiOptions::INCLUDE_COUNT)) {
                $meta['count'] = $modelClass::countByRequest($criteria);
            }
        }

        if ($this->request->getParameterAsBool(ApiOptions::INCLUDE_SCHEMA)) {
            /** @var BaseSystemModel $model */
            $model = $this->getModel();
            $meta['schema'] = $model->getTableSchema()->toArray();
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $result['meta'] = $meta;
        }

        return $result;
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
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;

        if (!empty($this->resource)) {
            throw new BadRequestException('Create record by identifier not currently supported.');
        }

        $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.' . ResourcesWrapper::getWrapperMsg());
        }

        $result = $modelClass::bulkCreate($records, $this->request->getParameters());

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL);

        return ResponseFactory::create($result, null, ServiceResponseInterface::HTTP_CREATED);
    }

    /**
     * Handles PATCH action
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePATCH()
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $options = $this->request->getParameters();

        if (!empty($this->resource)) {
            return $modelClass::updateById($this->resource, $this->getPayloadData(), $options);
        }

        $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.' . ResourcesWrapper::getWrapperMsg());
        }

        if (!empty($ids = array_get($options, ApiOptions::IDS))) {
            $record = array_get($records, 0, $records);
            $result = $modelClass::updateByIds($ids, $record, $options);
        } else {
            $filter = array_get($options, ApiOptions::FILTER);
            if (!empty($filter)) {
                $record = array_get($records, 0, $records);
                $params = array_get($options, ApiOptions::PARAMS, []);
                $result = $modelClass::updateByFilter(
                    $record,
                    $filter,
                    $params,
                    $options
                );
            } else {
                $result = $modelClass::bulkUpdate($records, $options);
            }
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL);

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
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $options = $this->request->getParameters();

        if (!empty($this->resource)) {
            return $modelClass::deleteById($this->resource, $options);
        }

        if (!empty($ids = array_get($options, ApiOptions::IDS))) {
            $result = $modelClass::deleteByIds($ids, $options);
        } else {
            $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
            if (!empty($records)) {
                $result = $modelClass::bulkDelete($records, $options);
            } else {
                $filter = array_get($options, ApiOptions::FILTER);
                if (!empty($filter)) {
                    $params = array_get($options, ApiOptions::PARAMS, []);
                    $result = $modelClass::deleteByFilter($filter, $params, $options);
                } else {
                    if (!array_get_bool($options, ApiOptions::FORCE)) {
                        throw new BadRequestException('No filter or records given for delete request.');
                    }

                    return $modelClass::truncate($options);
                }
            }
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $idField, ApiOptions::FIELDS_ALL);

        return $result;
    }

    /**
     * Returns associated model with the service/resource.
     *
     * @return \DreamFactory\Core\Models\BaseSystemModel
     * @throws ModelNotFoundException
     */
    protected function getModel()
    {
        if (empty(static::$model) || !class_exists(static::$model)) {
            throw new ModelNotFoundException();
        }

        return new static::$model;
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = camel_case($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $pluralClass = str_plural($class);
        if ($pluralClass === $class) {
            // method names can't be the same
            $pluralClass = $class . 'Entries';
        }
        $path = '/' . $serviceName . '/' . $resourceName;
        $wrapper = ResourcesWrapper::getWrapper();

        $apis = [
            $path           => [
                'get'    => [
                    'tags'        => [$serviceName],
                    'summary'     => 'get' .
                        $capitalized .
                        $pluralClass .
                        '() - Retrieve one or more ' .
                        $pluralClass .
                        '.',
                    'operationId' => 'get' . $capitalized . $pluralClass,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                        ApiOptions::documentOption(ApiOptions::LIMIT),
                        ApiOptions::documentOption(ApiOptions::ORDER),
                        ApiOptions::documentOption(ApiOptions::GROUP),
                        ApiOptions::documentOption(ApiOptions::OFFSET),
                        ApiOptions::documentOption(ApiOptions::COUNT_ONLY),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_COUNT),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_SCHEMA),
                        ApiOptions::documentOption(ApiOptions::FILE),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Response']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
                        'By default, all records up to the maximum are returned. <br>' .
                        'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                        'By default, all fields and no relations are returned for each record. <br>' .
                        'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
                        'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
                ],
                'post'   => [
                    'tags'        => [$serviceName],
                    'summary'     => 'create' .
                        $capitalized .
                        $pluralClass .
                        '() - Create one or more ' .
                        $pluralClass .
                        '.',
                    'operationId' => 'create' . $capitalized . $pluralClass,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of records to create.',
                            'in'          => 'body',
                            'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Request'],
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        [
                            'name'        => 'X-HTTP-METHOD',
                            'description' => 'Override request using POST to tunnel other http request, such as DELETE.',
                            'enum'        => ['GET', 'PUT', 'PATCH', 'DELETE'],
                            'type'        => 'string',
                            'in'          => 'header',
                            'required'    => false,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $pluralClass .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record affected is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                ],
                'patch'  => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' .
                        $capitalized .
                        $pluralClass .
                        '() - Update one or more ' .
                        $pluralClass .
                        '.',
                    'operationId' => 'update' . $capitalized . $pluralClass,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of records to update.',
                            'in'          => 'body',
                            'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Request'],
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $pluralClass .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                ],
                'put'  => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' .
                        $capitalized .
                        $pluralClass .
                        '() - Replace one or more ' .
                        $pluralClass .
                        '.',
                    'operationId' => 'replace' . $capitalized . $pluralClass,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of records to update.',
                            'in'          => 'body',
                            'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Request'],
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $pluralClass .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                ],
                'delete' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' .
                        $capitalized .
                        $pluralClass .
                        '() - Delete one or more ' .
                        $pluralClass .
                        '.',
                    'operationId' => 'delete' . $capitalized . $pluralClass,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        [
                            'name'        => 'force',
                            'description' => 'Set force to true to delete all records in this table, otherwise \'ids\' parameter is required.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'required'    => false,
                            'default'     => false,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $pluralClass .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'By default, only the id property of the record deleted is returned on success. ' .
                        'Use \'fields\' and \'related\' to return more properties of the deleted records. <br>' .
                        'Alternatively, to delete by record or a large list of ids, ' .
                        'use the POST request with X-HTTP-METHOD = DELETE header and post records or ids.',
                ],
            ],
            $path . '/{id}' => [
                'parameters' => [
                    [
                        'name'        => 'id',
                        'description' => 'Identifier of the record to retrieve.',
                        'type'        => 'string',
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'tags'        => [$serviceName],
                    'summary'     => 'get' . $capitalized . $class . '() - Retrieve one ' . $class . '.',
                    'operationId' => 'get' . $capitalized . $class,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $class .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
                ],
                'patch'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'update' . $capitalized . $class . '() - Update one ' . $class . '.',
                    'operationId' => 'update' . $capitalized . $class,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of fields to update.',
                            'in'          => 'body',
                            'schema'      => ['$ref' => '#/definitions/' . $class . 'Request'],
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $class .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'Post data should be an array of fields to update for a single record. <br>' .
                        'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
                ],
                'put'      => [
                    'tags'        => [$serviceName],
                    'summary'     => 'replace' . $capitalized . $class . '() - Replace one ' . $class . '.',
                    'operationId' => 'replace' . $capitalized . $class,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of fields to update.',
                            'in'          => 'body',
                            'schema'      => ['$ref' => '#/definitions/' . $class . 'Request'],
                            'required'    => true,
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $class .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'Post data should be an array of fields to update for a single record. <br>' .
                        'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
                ],
                'delete'     => [
                    'tags'        => [$serviceName],
                    'summary'     => 'delete' . $capitalized . $class . '() - Delete one ' . $class . '.',
                    'operationId' => 'delete' . $capitalized . $class,
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => [
                                '$ref' => '#/definitions/' .
                                    $class .
                                    'Response'
                            ]
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return deleted properties.',
                ],
            ],
        ];

        $models = [
            $pluralClass . 'Request'  => [
                'type'       => 'object',
                'properties' => [
                    $wrapper        => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => '#/definitions/' . $class . 'Request',
                        ],
                    ],
                    ApiOptions::IDS => [
                        'type'        => 'array',
                        'description' => 'Array of system record identifiers, used for batch GET, PUT, PATCH, and DELETE.',
                        'items'       => [
                            'type'   => 'integer',
                            'format' => 'int32',
                        ],
                    ],
                ],
            ],
            $pluralClass . 'Response' => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => '#/definitions/' . $class . 'Response',
                        ],
                    ],
                    'meta'   => [
                        '$ref' => '#/definitions/Metadata',
                    ],
                ],
            ],
            'Metadata'                => [
                'type'       => 'object',
                'properties' => [
                    'schema' => [
                        'type'        => 'array',
                        'description' => 'Array of table schema.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                    'count'  => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Record count returned for GET requests.',
                    ],
                ],
            ],
        ];

        if (!empty(static::$model) && class_exists(static::$model)) {
            /** @type BaseSystemModel $model */
            $model = new static::$model;
            if ($model) {
                $temp = $model->toApiDocsModel($class);
                if ($temp) {
                    $models = array_merge($models, $temp);
                }
            }
        }

        return ['paths' => $apis, 'definitions' => $models];
    }
}