<?php
namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\DbRequestCriteria;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Models\EventSubscriber;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Library\Utility\Inflector;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Event Service
 */
class Event extends BaseRestService
{
    use DbRequestCriteria;

    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default maximum records returned on filter request
     */
    const MAX_RECORDS_RETURNED = 1000;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = EventSubscriber::class;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);
    }

    /**
     * {@inheritdoc}
     */
    protected function getPayloadData($key = null, $default = null)
    {
        $payload = parent::getPayloadData();

        if (null !== $key && !empty($payload[$key])) {
            return $payload[$key];
        }

//        $alwaysWrap = \Config::get('df.always_wrap_resources', false);
        $wrapper = ResourcesWrapper::getWrapper();
        if (!empty($this->resource) && !empty($payload)) {
            // single records passed in which don't use the record wrapper, so wrap it
            $payload = [$wrapper => [$payload]];
        } elseif (ArrayUtils::isArrayNumeric($payload)) {
            // import from csv, etc doesn't include a wrapper, so wrap it
            $payload = [$wrapper => $payload];
        }

        if (empty($key)) {
            $key = $wrapper;
        }

        return ArrayUtils::get($payload, $key);
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $wrapper = ResourcesWrapper::getWrapper();
        $ids = $this->request->getParameter(ApiOptions::IDS);
        $records = ResourcesWrapper::unwrapResources($this->getPayloadData());

        $data = null;

        $related = $this->request->getParameter('related');
        if (!empty($related)) {
            $related = explode(',', $related);
        } else {
            $related = [];
        }

        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->getModel();
        /** @var BaseSystemModel $model */
        $model = new $modelClass;
        $pk = $model->getPrimaryKey();

        //	Single resource by ID
        if (!empty($this->resource)) {
            $foundModel = $modelClass::with($related)->find($this->resource);
            if ($foundModel) {
                $data = $foundModel->toArray();
            }
        } else if (!empty($ids)) {
            /** @var Collection $dataCol */
            $dataCol = $modelClass::with($related)->whereIn($pk, explode(',', $ids))->get();
            $data = $dataCol->toArray();
            $data = ResourcesWrapper::wrapResources($data);
        } else if (!empty($records)) {
            $pk = $model->getPrimaryKey();
            $ids = [];

            foreach ($records as $record) {
                $ids[] = ArrayUtils::get($record, $pk);
            }

            /** @var Collection $dataCol */
            $dataCol = $modelClass::with($related)->whereIn($pk, $ids)->get();
            $data = $dataCol->toArray();
            $data = ResourcesWrapper::wrapResources($data);
        } else {
            //	Build our criteria
            $criteria = $this->getSelectionCriteria();
            $data = $model->selectByRequest($criteria, $related);
            $data = ResourcesWrapper::wrapResources($data);
        }

        if ($this->request->getParameterAsBool(ApiOptions::INCLUDE_COUNT)) {
            if (isset($data[$wrapper])) {
                $data['meta']['count'] = count($data[$wrapper]);
            } elseif (!empty($data)) {
                $data['meta']['count'] = 1;
            }
        }

        if (!empty($data) && $this->request->getParameterAsBool(ApiOptions::INCLUDE_SCHEMA)) {
            $data['meta']['schema'] = $model->getTableSchema()->toArray();
        }

        return ResponseFactory::create($data);
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

        $this->triggerActionEvent($this->response);

        $model = $this->getModel();
        $result = $model::bulkCreate($records, $this->request->getParameters());

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
     * Handles PATCH action
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePATCH()
    {
        $modelClass = $this->getModel();

        $this->triggerActionEvent($this->response);

        if (!empty($this->resource)) {
            $result =
                $modelClass::updateById($this->resource, $this->getPayloadData(), $this->request->getParameters());
        } elseif (!empty($ids = $this->request->getParameter(ApiOptions::IDS))) {
            $records = ResourcesWrapper::unwrapResources($this->getPayloadData());
            if (empty($records)) {
                throw new BadRequestException('No record(s) detected in request.');
            }
            $result = $modelClass::updateByIds($ids, $records[0], $this->request->getParameters());
        } elseif (!empty($records = ResourcesWrapper::unwrapResources($this->getPayloadData()))) {
            $result = $modelClass::bulkUpdate($records, $this->request->getParameters());
        } else {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $id, ApiOptions::FIELDS_ALL);

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
        $this->triggerActionEvent($this->response);
        $modelClass = $this->getModel();

        if (!empty($this->resource)) {
            $result = $modelClass::deleteById($this->resource, $this->request->getParameters());
        } elseif (!empty($ids = $this->request->getParameter(ApiOptions::IDS))) {
            $result = $modelClass::deleteByIds($ids, $this->request->getParameters());
        } elseif ($records = ResourcesWrapper::unwrapResources($this->getPayloadData())) {
            if (isset($records[0]) && is_array($records[0])) {
                $result = $modelClass::bulkDelete($records, $this->request->getParameters());
            } else {
                // this may be a list of ids
                $result = $modelClass::deleteByIds($records, $this->request->getParameters());
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

    public static function getApiDocInfo(Service $service)
    {
        $wrapper = ResourcesWrapper::getWrapper();
        $name = strtolower($service->name);
        $capitalized = Inflector::camelize($service->name);

        $apis = [
            '/' . $name           => [
                'parameters' => [
                    ApiOptions::documentOption(ApiOptions::FIELDS),
                    ApiOptions::documentOption(ApiOptions::RELATED),
                ],
                'get'        => [
                    'tags'        => [$name],
                    'summary'     => 'get' . $capitalized . 'Subscribers() - Retrieve one or more subscribers.',
                    'operationId' => 'get' . $capitalized . 'Subscribers',
                    'event_name'  => $name . '.subscriber.list',
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
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
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/SubscribersResponse']
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
                'post'       => [
                    'tags'        => [$name],
                    'summary'     => 'create' . $capitalized . 'Subscribers() - Create one or more subscribers.',
                    'operationId' => 'create' . $capitalized . 'Subscribers',
                    'event_name'  => $name . '.subscriber.create',
                    'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                    'produces'    => ['application/json', 'application/xml', 'text/csv'],
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of records to create.',
                            'schema'      => ['$ref' => '#/definitions/SubscribersRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                        [
                            'name'        => 'X-HTTP-METHOD',
                            'description' => 'Override request using POST to tunnel other http request, such as DELETE.',
                            'enum'        => ['GET', 'PUT', 'PATCH', 'DELETE'],
                            'type'        => 'string',
                            'in'          => 'header',
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/SubscribersResponse']
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
                'patch'      => [
                    'tags'        => [$name],
                    'summary'     => 'update' . $capitalized . 'Subscribers() - Update one or more subscribers.',
                    'operationId' => 'update' . $capitalized . 'Subscribers',
                    'event_name'  => $name . '.subscriber.update',
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of records to update.',
                            'schema'      => ['$ref' => '#/definitions/SubscribersRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/SubscribersResponse']
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
                'delete'     => [
                    'tags'        => [$name],
                    'summary'     => 'delete' . $capitalized . 'Subscribers() - Delete one or more subscribers.',
                    'operationId' => 'delete' . $capitalized . 'Subscribers',
                    'event_name'  => $name . '.subscriber.delete',
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FORCE),
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/SubscribersResponse']
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
            '/' . $name . '/{id}' => [
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
                    'tags'        => [$name],
                    'summary'     => 'get' . $capitalized . 'Subscriber() - Retrieve one subscriber.',
                    'operationId' => 'get' . $capitalized . 'Subscriber',
                    'event_name'  => $name . '.subscriber.read',
                    'parameters'  => [],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Subscriber']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Use the \'fields\' and/or \'related\' parameter to limit properties that are returned. By default, all fields and no relations are returned.',
                ],
                'patch'      => [
                    'tags'        => [$name],
                    'summary'     => 'update' . $capitalized . 'Subscriber() - Update one subscriber.',
                    'operationId' => 'update' . $capitalized . 'Subscriber',
                    'event_name'  => $name . '.subscriber.update',
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs of fields to update.',
                            'schema'      => ['$ref' => '#/definitions/Subscriber'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Subscriber']
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
                    'tags'        => [$name],
                    'summary'     => 'delete' . $capitalized . 'Subscriber() - Delete one subscriber.',
                    'operationId' => 'delete' . $capitalized . 'Subscriber',
                    'event_name'  => $name . '.subscriber.delete',
                    'parameters'  => [
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Subscriber']
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
            'SubscribersRequest'  => [
                'type'       => 'object',
                'properties' => [
                    $wrapper        => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => '#/definitions/Subscriber',
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
            'SubscribersResponse' => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => '#/definitions/Subscriber',
                        ],
                    ],
                    'meta'   => [
                        'type'        => 'Metadata',
                        'description' => 'Array of metadata returned for GET requests.',
                    ],
                ],
            ],
        ];

        return ['paths' => $apis, 'definitions' => $models];
    }
}
