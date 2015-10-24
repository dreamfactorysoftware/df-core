<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\DbRequestCriteria;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Models\BaseSystemModel;
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
    protected $model = null;

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::PATCH,
            Verbs::MERGE => Verbs::PATCH
        ];
        ArrayUtils::set($settings, "verbAliases", $verbAliases);

        parent::__construct($settings);

        $this->model = ArrayUtils::get($settings, "model_name", $this->model); // could be statically set
    }

    protected function getResourceIdentifier()
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
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
     */
    protected function retrieveById($id, array $related = [])
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $criteria = $this->getSelectionCriteria();
        $fields = ArrayUtils::get($criteria, 'select');
        $data = $modelClass::selectById($id, $related, $fields);

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
        $modelClass = $this->model;
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
        $modelClass = $this->model;
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
            $data = $this->retrieveByRequest($related);
            if ($this->request->getParameterAsBool(ApiOptions::INCLUDE_COUNT)) {
                $meta['count'] = count($data);
            }
        }

        if ($this->request->getParameterAsBool(ApiOptions::INCLUDE_SCHEMA)) {
            /** @var BaseSystemModel $model */
            $model = $this->getModel();
            $meta['schema'] = $model->getTableSchema()->toArray();
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, $this->getResourceIdentifier());
        $data = ResourcesWrapper::cleanResources($data, $asList, $id, ApiOptions::FIELDS_ALL, !empty($meta));

        if (!empty($meta)) {
            $data['meta'] = $meta;
        }

        return $data;
    }

    /**
     * Returns associated model with the service/resource.
     *
     * @return \DreamFactory\Core\Models\BaseSystemModel
     * @throws ModelNotFoundException
     */
    protected function getModel()
    {
        if (empty($this->model) || !class_exists($this->model)) {
            throw new ModelNotFoundException();
        }

        return new $this->model;
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $name = Inflector::camelize($this->name);
        $plural = Inflector::pluralize($name);
        $words = str_replace('_', ' ', $this->name);
        $pluralWords = Inflector::pluralize($words);
        $wrapper = ResourcesWrapper::getWrapper();

        $apis = [
            [
                'path'        => $path,
                'description' => "Operations for $words administration.",
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $plural . '() - Retrieve one or more ' . $pluralWords . '.',
                        'nickname'         => 'get' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => [$eventPath . '.list'],
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::FILTER),
                            ApiOptions::documentOption(ApiOptions::LIMIT),
                            ApiOptions::documentOption(ApiOptions::ORDER),
                            ApiOptions::documentOption(ApiOptions::GROUP),
                            ApiOptions::documentOption(ApiOptions::OFFSET),
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                            ApiOptions::documentOption(ApiOptions::INCLUDE_COUNT),
                            ApiOptions::documentOption(ApiOptions::INCLUDE_SCHEMA),
                            ApiOptions::documentOption(ApiOptions::FILE),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            =>
                            'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
                            'By default, all records up to the maximum are returned. <br>' .
                            'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                            'By default, all fields and no relations are returned for each record. <br>' .
                            'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
                            'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
                    ],
                ],
            ],
            [
                'path'        => $path . '/{id}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . '() - Retrieve one ' . $words . '.',
                        'nickname'         => 'get' . $name,
                        'type'             => $name . 'Response',
                        'event_name'       => $eventPath . '.read',
                        'parameters'       => [
                            [
                                'name'          => 'id',
                                'description'   => 'Identifier of the record to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
                    ],
                ],
                'description' => "Operations for individual $words administration.",
            ],
        ];

        $models = [
            $plural . 'Request'  => [
                'id'         => $plural . 'Request',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => $name . 'Request',
                        ],
                    ],
                    ApiOptions::IDS    => [
                        'type'        => 'array',
                        'description' => 'Array of system record identifiers, used for batch GET, PUT, PATCH, and DELETE.',
                        'items'       => [
                            'type'   => 'integer',
                            'format' => 'int32',
                        ],
                    ],
                ],
            ],
            $plural . 'Response' => [
                'id'         => $plural . 'Response',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => $name . 'Response',
                        ],
                    ],
                    'meta'   => [
                        'type'        => 'Metadata',
                        'description' => 'Array of metadata returned for GET requests.',
                    ],
                ],
            ],
            'Metadata'           => [
                'id'         => 'Metadata',
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

        $model = $this->getModel();
        if ($model) {
            $temp = $model->toApiDocsModel($name);
            if ($temp) {
                $models = array_merge($models, $temp);
            }
        }

        return ['apis' => $apis, 'models' => $models];
    }
}