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

    /**
     * @param $data
     *
     * @return array
     */
    protected static function removeEmptyAttributes($data)
    {
        $records = $data;
        $unwrapped = false;
        if (isset($data[ResourcesWrapper::getWrapper()])) {
            $records = $data[ResourcesWrapper::getWrapper()];
            $unwrapped = true;
        }

        foreach ($records as $i => $record) {
            if (is_numeric($i) && [$record]) {
                foreach ($record as $key => $value) {
                    if (empty($value)) {
                        unset($records[$i][$key]);
                    } elseif (is_string($value)) {
                        if (strtolower($value) === "true") {
                            $records[$i][$key] = true;
                        } elseif (strtolower($value) === "false") {
                            $records[$i][$key] = false;
                        } elseif (strtolower($value) === "null") {
                            $records[$i][$key] = null;
                        }
                    }
                }
            }
        }

        $data = $records;
        if ($unwrapped) {
            $data = ResourcesWrapper::wrapResources($data);
        }

        return $data;
    }

    protected function getApiDocPaths()
    {
        $service = $this->getServiceName();
        $capitalized = camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower($this->name);
        $pluralClass = str_plural($class);
        if ($pluralClass === $class) {
            // method names can't be the same
            $pluralClass = $class . 'Entries';
        }
        $path = '/' . $resourceName;

        $paths = [
            $path           => [
                'get'    => [
                    'summary'     => 'Retrieve one or more ' . $pluralClass . '.',
                    'description' => 'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
                        'By default, all records up to the maximum are returned. ' .
                        'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                        'By default, all fields and no relations are returned for each record. ' .
                        'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
                        'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
                    'operationId' => 'get' . $capitalized . $pluralClass,
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
                        '200' => ['$ref' => '#/components/responses/' . $pluralClass . 'Response']
                    ],
                ],
                'post'   => [
                    'summary'     => 'Create one or more ' . $pluralClass . '.',
                    'description' => 'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record affected is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                    'operationId' => 'create' . $capitalized . $pluralClass,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        [
                            'name'        => 'X-HTTP-METHOD',
                            'description' => 'Override request using POST to tunnel other http request, such as DELETE.',
                            'schema'      => ['type' => 'string', 'enum' => ['GET', 'PUT', 'PATCH', 'DELETE']],
                            'in'          => 'header',
                        ],
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/' . $pluralClass . 'Request'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $pluralClass . 'Response']
                    ],
                ],
                'patch'  => [
                    'summary'     => 'Update one or more ' . $pluralClass . '.',
                    'description' => 'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                    'operationId' => 'update' . $capitalized . $pluralClass,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/' . $pluralClass . 'Request'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $pluralClass . 'Response']
                    ],
                ],
                'put'    => [
                    'summary'     => 'Replace one or more ' . $pluralClass . '.',
                    'description' => 'Post data should be a single record or an array of records (shown). ' .
                        'By default, only the id property of the record is returned on success, ' .
                        'use \'fields\' and \'related\' to return more info.',
                    'operationId' => 'replace' . $capitalized . $pluralClass,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/' . $pluralClass . 'Request'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $pluralClass . 'Response']
                    ],
                ],
                'delete' => [
                    'summary'     => 'Delete one or more ' . $pluralClass . '.',
                    'description' => 'By default, only the id property of the record deleted is returned on success. ' .
                        'Use \'fields\' and \'related\' to return more properties of the deleted records. ' .
                        'Alternatively, to delete by record or a large list of ids, ' .
                        'use the POST request with X-HTTP-METHOD = DELETE header and post records or ids.',
                    'operationId' => 'delete' . $capitalized . $pluralClass,
                    'parameters'  => [
                        [
                            'name'        => 'force',
                            'description' => 'Set force to true to delete all records in this table, otherwise \'ids\' parameter is required.',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                        ],
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $pluralClass . 'Response']
                    ],
                ],
            ],
            $path . '/{id}' => [
                'parameters' => [
                    [
                        'name'        => 'id',
                        'description' => 'Identifier of the record to retrieve.',
                        'schema'      => ['type' => 'string'],
                        'in'          => 'path',
                        'required'    => true,
                    ],
                ],
                'get'        => [
                    'summary'     => 'Retrieve one ' . $class . '.',
                    'description' => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
                    'operationId' => 'get' . $capitalized . $class,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $class . 'Response']
                    ],
                ],
                'patch'      => [
                    'summary'     => 'Update one ' . $class . '.',
                    'description' => 'Post data should be an array of fields to update for a single record. ' .
                        'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
                    'operationId' => 'update' . $capitalized . $class,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/' . $class . 'Request'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $class . 'Response']
                    ],
                ],
                'put'        => [
                    'summary'     => 'Replace one ' . $class . '.',
                    'description' =>
                        'Post data should be an array of fields to update for a single record. ' .
                        'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
                    'operationId' => 'replace' . $capitalized . $class,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/' . $class . 'Request'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $class . 'Response']
                    ],
                ],
                'delete'     => [
                    'summary'     => 'Delete one ' . $class . '.',
                    'description' => 'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return deleted properties.',
                    'operationId' => 'delete' . $capitalized . $class,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $class . 'Response']
                    ],
                ],
            ],
        ];

        return $paths;
    }

    protected function getApiDocSchemas()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);
        if ($pluralClass === $class) {
            // method names can't be the same
            $pluralClass = $class . 'Entries';
        }
        $wrapper = ResourcesWrapper::getWrapper();

        $models = [
            $pluralClass . 'Request'  => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => '#/components/schemas/' . $class . 'Request',
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
                            '$ref' => '#/components/schemas/' . $class . 'Response',
                        ],
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

        return array_merge(parent::getApiDocSchemas(), $models);
    }

    protected function getApiDocRequests()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);
        if ($pluralClass === $class) {
            // method names can't be the same
            $pluralClass = $class . 'Entries';
        }

        $models = [
            $class . 'Request'       => [
                'description' => 'Request',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Request']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Request']
                    ],
                ],
            ],
            $pluralClass . 'Request' => [
                'description' => 'Request',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $pluralClass . 'Request']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $pluralClass . 'Request']
                    ],
                ],
            ],
        ];

        return array_merge(parent::getApiDocRequests(), $models);
    }

    protected function getApiDocResponses()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);
        if ($pluralClass === $class) {
            // method names can't be the same
            $pluralClass = $class . 'Entries';
        }

        $models = [
            $class . 'Response'       => [
                'description' => 'Response',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Response']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Response']
                    ],
                ],
            ],
            $pluralClass . 'Response' => [
                'description' => 'Response',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $pluralClass . 'Response']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $pluralClass . 'Response']
                    ],
                ],
            ],
        ];

        return array_merge(parent::getApiDocResponses(), $models);
    }
}