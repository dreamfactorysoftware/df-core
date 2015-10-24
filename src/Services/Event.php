<?php
namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\DbRequestCriteria;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Models\EventSubscriber;
use DreamFactory\Core\Utility\ResponseFactory;
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
    protected $model = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);
        $this->model = new EventSubscriber();
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

        return ResponseFactory::create($data, $this->nativeFormat);
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
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, $this->getResourceIdentifier());
        $result = ResourcesWrapper::cleanResources($result, $asList, $id, ApiOptions::FIELDS_ALL);

        return ResponseFactory::create($result, $this->nativeFormat, ServiceResponseInterface::HTTP_CREATED);
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
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, $this->getResourceIdentifier());
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
        $id = $this->request->getParameter(ApiOptions::ID_FIELD, $this->getResourceIdentifier());
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
        if (empty($this->model) || !class_exists($this->model)) {
            throw new ModelNotFoundException();
        }

        return new $this->model;
    }

    public function getApiDocInfo()
    {
//        $alwaysWrap = \Config::get('df.always_wrap_resources', false);
        $wrapper = ResourcesWrapper::getWrapper();

        $apis = [
            [
                'path'        => '/' . $this->name,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getEventSubscribers() - Retrieve one or more subscribers.',
                        'nickname'         => 'getEventSubscribers',
                        'type'             => 'SubscribersResponse',
                        'event_name'       => $this->name . '.subscriber.list',
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
                    [
                        'method'           => 'POST',
                        'summary'          => 'createEventSubscribers() - Create one or more subscribers.',
                        'nickname'         => 'createEventSubscribers',
                        'type'             => 'SubscribersResponse',
                        'event_name'       => $this->name . '.subscriber.create',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to create.',
                                'allowMultiple' => false,
                                'type'          => 'UsersRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                            [
                                'name'          => 'X-HTTP-METHOD',
                                'description'   => 'Override request using POST to tunnel other http request, such as DELETE.',
                                'enum'          => ['GET', 'PUT', 'PATCH', 'DELETE'],
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'header',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            =>
                            'Post data should be a single record or an array of records (shown). ' .
                            'By default, only the id property of the record affected is returned on success, ' .
                            'use \'fields\' and \'related\' to return more info.',
                    ],
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'updateEventSubscribers() - Update one or more subscribers.',
                        'nickname'         => 'updateEventSubscribers',
                        'type'             => 'SubscribersResponse',
                        'event_name'       => $this->name . '.subscriber.update',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to update.',
                                'allowMultiple' => false,
                                'type'          => 'UsersRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            =>
                            'Post data should be a single record or an array of records (shown). ' .
                            'By default, only the id property of the record is returned on success, ' .
                            'use \'fields\' and \'related\' to return more info.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteEventSubscribers() - Delete one or more subscribers.',
                        'nickname'         => 'deleteEventSubscribers',
                        'type'             => 'SubscribersResponse',
                        'event_name'       => $this->name . '.subscriber.delete',
                        'parameters'       => [
                            ApiOptions::documentOption(ApiOptions::IDS),
                            ApiOptions::documentOption(ApiOptions::FORCE),
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            =>
                            'By default, only the id property of the record deleted is returned on success. ' .
                            'Use \'fields\' and \'related\' to return more properties of the deleted records. <br>' .
                            'Alternatively, to delete by record or a large list of ids, ' .
                            'use the POST request with X-HTTP-METHOD = DELETE header and post records or ids.',
                    ],
                ],
                'description' => 'Operations for user administration.',
            ],
            [
                'path'        => '/' . $this->name . '/{id}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getEventSubscriber() - Retrieve one subscriber.',
                        'nickname'         => 'getEventSubscriber',
                        'type'             => 'Subscriber',
                        'event_name'       => $this->name . '.subscriber.read',
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
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'updateEventSubscriber() - Update one subscriber.',
                        'nickname'         => 'updateEventSubscriber',
                        'type'             => 'Subscriber',
                        'event_name'       => $this->name . '.subscriber.update',
                        'parameters'       => [
                            [
                                'name'          => 'id',
                                'description'   => 'Identifier of the record to update.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of fields to update.',
                                'allowMultiple' => false,
                                'type'          => 'UserRequest',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            =>
                            'Post data should be an array of fields to update for a single record. <br>' .
                            'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return more properties.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteEventSubscriber() - Delete one subscriber.',
                        'nickname'         => 'deleteEventSubscriber',
                        'type'             => 'Subscriber',
                        'event_name'       => $this->name . '.subscriber.delete',
                        'parameters'       => [
                            [
                                'name'          => 'id',
                                'description'   => 'Identifier of the record to delete.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::RELATED),
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'By default, only the id is returned. Use the \'fields\' and/or \'related\' parameter to return deleted properties.',
                    ],
                ],
                'description' => 'Operations for individual user administration.',
            ],
        ];

        $models = [
            'SubscribersRequest'  => [
                'id'         => 'SubscribersRequest',
                'properties' => [
                    $wrapper        => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => 'Subscriber',
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
                'id'         => 'SubscribersResponse',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => 'Subscriber',
                        ],
                    ],
                    'meta'   => [
                        'type'        => 'Metadata',
                        'description' => 'Array of metadata returned for GET requests.',
                    ],
                ],
            ],
        ];

        return ['apis' => $apis, 'models' => $models];
    }
}
