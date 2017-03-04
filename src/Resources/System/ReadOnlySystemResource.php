<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\DbRequestCriteria;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class ReadOnlySystemResource
 *
 * @package DreamFactory\Core\Resources
 */
class ReadOnlySystemResource extends BaseRestResource
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
                throw new NotFoundException("Record with identifier '{$this->resource}'not found.");
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
        $path = '/' . $serviceName . '/' . $resourceName;
//        $base = parent::getApiDocInfo($service, $resource);
        $wrapper = ResourcesWrapper::getWrapper();

        $apis = [
            $path           => [
                'parameters' => [
                    ApiOptions::documentOption(ApiOptions::FIELDS),
                    ApiOptions::documentOption(ApiOptions::RELATED),
                ],
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' .
                        $capitalized .
                        $pluralClass .
                        '() - Retrieve one or more ' .
                        $pluralClass .
                        '.',
                    'operationId'       => 'get' . $capitalized . $pluralClass,
                    'consumes'          => ['application/json', 'application/xml', 'text/csv'],
                    'produces'          => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'        => [
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
                    ApiOptions::documentOption(ApiOptions::FIELDS),
                    ApiOptions::documentOption(ApiOptions::RELATED),
                ],
                'get'        => [
                    'tags'              => [$serviceName],
                    'summary'           => 'get' . $capitalized . $class . '() - Retrieve one ' . $class . '.',
                    'operationId'       => 'get' . $capitalized . $class,
                    'parameters'        => [],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/' . $class . 'Response']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
                ],
            ],
        ];

        $models = [
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