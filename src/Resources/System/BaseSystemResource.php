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
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
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
            Verbs::PUT   => Verbs::PATCH,
            Verbs::MERGE => Verbs::PATCH
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
     * Retrieves records by id.
     *
     * @param integer $id
     * @param array   $related
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function retrieveById($id, array $related = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $criteria = $this->getSelectionCriteria();
        $fields = array_get($criteria, 'select');
        if (empty($data = $modelClass::selectById($id, $related, $fields))) {
            throw new NotFoundException('Record not found');
        }

        return $data;
    }

    /**
     * Retrieves records by ids.
     *
     * @param mixed $ids
     * @param array $related
     *
     * @return array
     */
    protected function retrieveByIds($ids, array $related = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $criteria = $this->getSelectionCriteria();
        $data = $modelClass::selectByIds($ids, $related, $criteria);

        return $data;
    }

    protected function retrieveByRecords(array $records, array $related = [])
    {
        /** @var BaseSystemModel $model */
        $model = $this->getModel();
        $pk = $model->getPrimaryKey();
        $ids = array_column($records, $pk);

        return $this->retrieveByIds($ids, $related);
    }

    /**
     * Retrieves records by criteria/filters.
     *
     * @param array $related
     *
     * @return array
     */
    protected function retrieveByRequest(array $related = [])
    {
        /** @type BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $criteria = $this->getSelectionCriteria();
        $data = $modelClass::selectByRequest($criteria, $related);

        return $data;
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $data = null;

        $related = $this->request->getParameter(ApiOptions::RELATED);
        if (!empty($related)) {
            $related = explode(',', $related);
        } else {
            $related = [];
        }

        $meta = [];
        if (!empty($this->resource)) {
            //	Single resource by ID
            $data = $this->retrieveById($this->resource, $related);
        } else if (!empty($ids = $this->request->getParameter(ApiOptions::IDS))) {
            $data = $this->retrieveByIds($ids, $related);
        } else if (!empty($records = ResourcesWrapper::unwrapResources($this->getPayloadData()))) {
            if (isset($records[0]) && is_array($records[0])) {
                $data = $this->retrieveByRecords($records, $related);
            } else {
                // this may be a list of ids
                $data = $this->retrieveByIds($ids, $related);
            }
        } else {
            /** @type BaseSystemModel $modelClass */
            $modelClass = static::$model;
            $criteria = $this->getSelectionCriteria();
            $data = $modelClass::selectByRequest($criteria, $related);
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
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $data = ResourcesWrapper::cleanResources($data, $asList, $id, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $data['meta'] = $meta;
        }

        return $data;
    }

    /**
     * Creates new records in bulk.
     *
     * @param array $records
     * @param array $params
     *
     * @return mixed
     */
    protected function bulkCreate(array $records, array $params = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $result = $modelClass::bulkCreate($records, $params);

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
        if (!empty($this->resource)) {
            throw new BadRequestException('Create record by identifier not currently supported.');
        }

        $records = ResourcesWrapper::unwrapResources($this->getPayloadData());

        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $result = $this->bulkCreate($records, $this->request->getParameters());

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $id, ApiOptions::FIELDS_ALL);

        return ResponseFactory::create($result, null, ServiceResponseInterface::HTTP_CREATED);
    }

    /**
     * @throws BadRequestException
     */
    protected function handlePUT()
    {
        throw new BadRequestException('PUT is not supported on System Resource. Use PATCH');
    }

    /**
     * Updates record by id.
     *
     * @param integer $id
     * @param array   $record
     * @param array   $params
     *
     * @return mixed
     */
    protected function updateById($id, array $record, array $params = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $result = $modelClass::updateById($id, $record, $params);

        return $result;
    }

    /**
     * Updates records by ids.
     *
     * @param array|string $ids
     * @param array        $record
     * @param array        $params
     *
     * @return mixed
     */
    protected function updateByIds($ids, array $record, array $params = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $result = $modelClass::updateByIds($ids, $record, $params);

        return $result;
    }

    /**
     * Bulk updates records.
     *
     * @param                 $records
     * @param array           $params
     *
     * @return mixed
     */
    protected function bulkUpdate(array $records, array $params = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $result = $modelClass::bulkUpdate($records, $params);

        return $result;
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
        if (!empty($this->resource)) {
            $result = $this->updateById($this->resource, $this->getPayloadData(), $this->request->getParameters());
        } elseif (!empty($ids = $this->request->getParameter(ApiOptions::IDS))) {
            $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
            if (empty($records)) {
                throw new BadRequestException('No record(s) detected in request.');
            }
            $result = $this->updateByIds($ids, $records[0], $this->request->getParameters());
        } elseif (!empty($records = ResourcesWrapper::unwrapResources($this->getPayloadData()))) {
            $result = $this->bulkUpdate($records, $this->request->getParameters());
        } else {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $id, ApiOptions::FIELDS_ALL);

        return $result;
    }

    /**
     * Deletes a record by id.
     *
     * @param integer $id
     * @param array   $params
     *
     * @return mixed
     */
    protected function deleteById($id, array $params = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $result = $modelClass::deleteById($id, $params);

        return $result;
    }

    /**
     * Deletes records by ids.
     *
     * @param array|string $ids
     * @param array        $params
     *
     * @return mixed
     */
    protected function deleteByIds($ids, array $params = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $result = $modelClass::deleteByIds($ids, $params);

        return $result;
    }

    /**
     * Deletes records.
     *
     * @param array $records
     * @param array $params
     *
     * @return mixed
     */
    protected function bulkDelete(array $records, array $params = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = static::$model;
        $result = $modelClass::bulkDelete($records, $params);

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
        if (!empty($this->resource)) {
            $result = $this->deleteById($this->resource, $this->request->getParameters());
        } elseif (!empty($ids = $this->request->getParameter(ApiOptions::IDS))) {
            $result = $this->deleteByIds($ids, $this->request->getParameters());
        } elseif ($records = ResourcesWrapper::unwrapResources($this->getPayloadData())) {
            if (isset($records[0]) && is_array($records[0])) {
                $result = $this->bulkDelete($records, $this->request->getParameters());
            } else {
                // this may be a list of ids
                $result = $this->deleteByIds($records, $this->request->getParameters());
            }
        } else {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $id, ApiOptions::FIELDS_ALL);

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
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $pluralClass = Inflector::pluralize($class);
        if ($pluralClass === $class) {
            // method names can't be the same
            $pluralClass = $class . 'Entries';
        }
        $path = '/' . $serviceName . '/' . $resourceName;
        $eventPath = $serviceName . '.' . $resourceName;
        $wrapper = ResourcesWrapper::getWrapper();

        $apis = [
            $path           => [
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' .
                        $capitalized .
                        $pluralClass .
                        '() - Retrieve one or more ' .
                        $pluralClass .
                        '.',
                    'operationId'       => 'get' . $capitalized . $pluralClass,
                    'x-publishedEvents' => [$eventPath . '.list'],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                        ApiOptions::documentOption(ApiOptions::LIMIT),
                        ApiOptions::documentOption(ApiOptions::ORDER),
                        ApiOptions::documentOption(ApiOptions::GROUP),
                        ApiOptions::documentOption(ApiOptions::OFFSET),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_COUNT),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_SCHEMA),
                        ApiOptions::documentOption(ApiOptions::FILE),
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Response']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       =>
                        'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
                        'By default, all records up to the maximum are returned. <br>' .
                        'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                        'By default, all fields and no relations are returned for each record. <br>' .
                        'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
                        'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
                ],
                'post'       => [
                    'tags'              => [$serviceName],
                    'summary'           => 'create' .
                        $capitalized .
                        $pluralClass .
                        '() - Create one or more ' .
                        $pluralClass .
                        '.',
                    'operationId'       => 'create' . $capitalized . $pluralClass,
                    'x-publishedEvents' => [$eventPath . '.create'],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
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
                    'responses'         => [
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
                    'description'       =>
                        'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record affected is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                ],
                'patch'      => [
                    'tags'              => [$serviceName],
                    'summary'           => 'update' .
                        $capitalized .
                        $pluralClass .
                        '() - Update one or more ' .
                        $pluralClass .
                        '.',
                    'operationId'       => 'update' . $capitalized . $pluralClass,
                    'x-publishedEvents' => [$eventPath . '.update'],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
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
                    'responses'         => [
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
                    'description'       =>
                        'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                ],
                'delete'     => [
                    'tags'              => [$serviceName],
                    'summary'           => 'delete' .
                        $capitalized .
                        $pluralClass .
                        '() - Delete one or more ' .
                        $pluralClass .
                        '.',
                    'operationId'       => 'delete' . $capitalized . $pluralClass,
                    'x-publishedEvents' => [$eventPath . '.delete'],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
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
                    'responses'         => [
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
                    'description'       =>
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
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . $class . '() - Retrieve one ' . $class . '.',
                    'operationId'       => 'get' . $capitalized . $class,
                    'x-publishedEvents' => [$eventPath . '.read'],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'         => [
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
                    'description'       => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
                ],
                'patch'      => [
                    'tags'              => [$serviceName],
                    'summary'           => 'update' . $capitalized . $class . '() - Update one ' . $class . '.',
                    'operationId'       => 'update' . $capitalized . $class,
                    'x-publishedEvents' => [$eventPath . '.update'],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
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
                    'responses'         => [
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
                    'description'       =>
                        'Post data should be an array of fields to update for a single record. <br>' .
                        'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
                ],
                'delete'     => [
                    'tags'              => [$serviceName],
                    'summary'           => 'delete' . $capitalized . $class . '() - Delete one ' . $class . '.',
                    'operationId'       => 'delete' . $capitalized . $class,
                    'x-publishedEvents' => [$eventPath . '.delete'],
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'         => [
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
                    'description'       => 'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return deleted properties.',
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