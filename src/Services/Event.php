<?php
namespace DreamFactory\Core\Services;

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
        $wrapper = \Config::get('df.resources_wrapper', 'resource');
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
        $alwaysWrap = \Config::get('df.always_wrap_resources', false);
        $wrapper = \Config::get('df.resources_wrapper', 'resource');
        $ids = $this->request->getParameter('ids');
        $records = $this->getPayloadData(($alwaysWrap ? $wrapper : null), []);

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
            $data = static::cleanResources($data);
        } else if (!empty($records)) {
            $pk = $model->getPrimaryKey();
            $ids = [];

            foreach ($records as $record) {
                $ids[] = ArrayUtils::get($record, $pk);
            }

            /** @var Collection $dataCol */
            $dataCol = $modelClass::with($related)->whereIn($pk, $ids)->get();
            $data = $dataCol->toArray();
            $data = static::cleanResources($data);
        } else {
            //	Build our criteria
            $criteria = [
                'params' => [],
            ];

            if (null !== ($value = $this->request->getParameter('fields'))) {
                $criteria['select'] = $value;
            } else {
                $criteria['select'] = "*";
            }

            if (null !== ($value = $this->request->getPayloadData('params'))) {
                $criteria['params'] = $value;
            }

            if (null !== ($value = $this->request->getParameter('filter'))) {
                $criteria['condition'] = $value;

                //	Add current user ID into parameter array if in condition, but not specified.
                if (false !== stripos($value, ':user_id')) {
                    if (!isset($criteria['params'][':user_id'])) {
                        //$criteria['params'][':user_id'] = Session::getCurrentUserId();
                    }
                }
            }

            $value = intval($this->request->getParameter('limit'));
            $maxAllowed = intval(\Config::get('df.db_max_records_returned', self::MAX_RECORDS_RETURNED));
            if (($value < 1) || ($value > $maxAllowed)) {
                // impose a limit to protect server
                $value = $maxAllowed;
            }
            $criteria['limit'] = $value;

            if (null !== ($value = $this->request->getParameter('offset'))) {
                $criteria['offset'] = $value;
            }

            if (null !== ($value = $this->request->getParameter('order'))) {
                $criteria['order'] = $value;
            }

            $data = $model->selectByRequest($criteria, $related);
            $data = static::cleanResources($data);
        }

        if (null === $data) {
            throw new NotFoundException("Record not found.");
        }

        if ($this->request->getParameterAsBool('include_count') === true) {
            if (isset($data[$wrapper])) {
                $data['meta']['count'] = count($data[$wrapper]);
            } elseif (!empty($data)) {
                $data['meta']['count'] = 1;
            }
        }

        if (!empty($data) && $this->request->getParameterAsBool('include_schema') === true) {
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

        $alwaysWrap = \Config::get('df.always_wrap_resources', false);
        $wrapper = \Config::get('df.resources_wrapper', 'resource');
        $records = $this->getPayloadData(($alwaysWrap ? $wrapper : null), []);

        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $this->triggerActionEvent($this->response);

        $model = $this->getModel();
        $result = $model::bulkCreate($records, $this->request->getParameters());

        $response = ResponseFactory::create($result, $this->nativeFormat, ServiceResponseInterface::HTTP_CREATED);

        return $response;
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
        $alwaysWrap = \Config::get('df.always_wrap_resources', false);
        $wrapper = \Config::get('df.resources_wrapper', 'resource');
        $records = $this->getPayloadData(($alwaysWrap ? $wrapper : null), []);
        $ids = $this->request->getParameter('ids');
        $modelClass = $this->getModel();

        if (empty($records)) {
            throw new BadRequestException('No record(s) detected in request.');
        }

        $this->triggerActionEvent($this->response);

        if (!empty($this->resource)) {
            $result = $modelClass::updateById($this->resource, $records[0], $this->request->getParameters());
        } elseif (!empty($ids)) {
            $result = $modelClass::updateByIds($ids, $records[0], $this->request->getParameters());
        } else {
            $result = $modelClass::bulkUpdate($records, $this->request->getParameters());
        }

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
        $ids = $this->request->getParameter('ids');
        $modelClass = $this->getModel();

        if (!empty($this->resource)) {
            $result = $modelClass::deleteById($this->resource, $this->request->getParameters());
        } elseif (!empty($ids)) {
            $result = $modelClass::deleteByIds($ids, $this->request->getParameters());
        } else {
            $alwaysWrap = \Config::get('df.always_wrap_resources', false);
            $wrapper = \Config::get('df.resources_wrapper', 'resource');
            $records = $this->getPayloadData(($alwaysWrap ? $wrapper : null), []);

            if (empty($records)) {
                throw new BadRequestException('No record(s) detected in request.');
            }
            $result = $modelClass::bulkDelete($records, $this->request->getParameters());
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
        if (empty($this->model)) {
            throw new ModelNotFoundException();
        }

        return $this->model;
    }

    public function getApiDocInfo()
    {
//        $alwaysWrap = \Config::get('df.always_wrap_resources', false);
        $wrapper = \Config::get('df.resources_wrapper', 'resource');

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
                            [
                                'name'          => 'ids',
                                'description'   => 'Comma-delimited list of the identifiers of the records to retrieve.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'filter',
                                'description'   => 'SQL-like filter to limit the records to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'limit',
                                'description'   => 'Set to limit the filter results.',
                                'allowMultiple' => false,
                                'type'          => 'integer',
                                'format'        => 'int32',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'order',
                                'description'   => 'SQL-like order containing field and direction for filter results.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'offset',
                                'description'   => 'Set to offset the filter results to a particular record count.',
                                'allowMultiple' => false,
                                'type'          => 'integer',
                                'format'        => 'int32',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to retrieve for each record.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to retrieve for each record.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'include_count',
                                'description'   => 'Include the total number of filter results in returned metadata.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'include_schema',
                                'description'   => 'Include the schema of the table queried in returned metadata.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'file',
                                'description'   => 'Download the results of the request as a file.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
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
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                            [
                                'name'          => 'ids',
                                'description'   => 'Comma-delimited list of the identifiers of the records to delete.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'force',
                                'description'   => 'Set force to true to delete all records in this table, otherwise \'ids\' parameter is required.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                                'default'       => false,
                            ],
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related names to return for each record affected.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related records to return.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related records to return.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                            [
                                'name'          => 'fields',
                                'description'   => 'Comma-delimited list of field names to return.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                            [
                                'name'          => 'related',
                                'description'   => 'Comma-delimited list of related records to return.',
                                'allowMultiple' => true,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => 'Subscriber',
                        ],
                    ],
                    'ids'    => [
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
