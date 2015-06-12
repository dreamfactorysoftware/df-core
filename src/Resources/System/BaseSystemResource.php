<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Utility\Session;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class BaseSystemResource
 *
 * @package DreamFactory\Core\Resources
 */
class BaseSystemResource extends BaseRestResource
{
    /**
     *
     */
    const RECORD_WRAPPER = 'record';
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
    public function __construct( $settings = [ ] )
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::PATCH,
            Verbs::MERGE => Verbs::PATCH
        ];
        ArrayUtils::set( $settings, "verbAliases", $verbAliases );

        parent::__construct( $settings );

        $this->model = ArrayUtils::get( $settings, "model_name", $this->model ); // could be statically set
    }

    /**
     * {@inheritdoc}
     */
    protected function getPayloadData( $key = null, $default = null )
    {
        $payload = parent::getPayloadData();

        if ( null !== $key && !empty( $payload[$key] ) )
        {
            return $payload[$key];
        }

        if ( !empty( $this->resource ) && !empty( $payload ) )
        {
            // single records passed in which don't use the record wrapper, so wrap it
            $payload = [ static::RECORD_WRAPPER => [ $payload ] ];
        }
        elseif ( ArrayUtils::isArrayNumeric( $payload ) )
        {
            // import from csv, etc doesn't include a wrapper, so wrap it
            $payload = [ static::RECORD_WRAPPER => $payload ];
        }

        if ( empty( $key ) )
        {
            $key = static::RECORD_WRAPPER;
        }

        return ArrayUtils::get( $payload, $key );
    }

    /**
     * Retrieves records by id.
     *
     * @param integer $id
     * @param array   $related
     *
     * @return array
     */
    protected function retrieveById( $id, array $related = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $criteria = $this->getSelectionCriteria();
        $fields = ArrayUtils::get( $criteria, 'select' );
        $data = $modelClass::selectById( $id, $related, $fields );

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
    protected function retrieveByIds( $ids, array $related = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $criteria = $this->getSelectionCriteria();
        $data = $modelClass::selectByIds( $ids, $related, $criteria );
        $data = [ self::RECORD_WRAPPER => $data ];

        return $data;
    }

    protected function retrieveByRecords( array $records, array $related = [ ] )
    {
        /** @var BaseSystemModel $model */
        $model = $this->getModel();
        $pk = $model->getPrimaryKey();
        $ids = [ ];
        foreach ( $records as $record )
        {
            $ids[] = ArrayUtils::get( $record, $pk );
        }

        return $this->retrieveByIds( $ids, $related );
    }

    /**
     * Retrieves records by criteria/filters.
     *
     * @param array $related
     *
     * @return array
     */
    protected function retrieveByRequest( array $related = [ ] )
    {
        /** @var BaseSystemModel $model */
        $modelClass = $this->model;
        $criteria = $this->getSelectionCriteria();
        $data = $modelClass::selectByRequest( $criteria, $related );
        $data = [ static::RECORD_WRAPPER => $data ];

        return $data;
    }

    /**
     * Builds the selection criteria from request and returns it.
     *
     * @return array
     */
    protected function getSelectionCriteria()
    {
        $criteria = [
            'params' => [ ]
        ];

        if ( null !== ( $value = $this->request->getParameter( 'fields' ) ) )
        {
            $criteria['select'] = explode( ',', $value );
        }
        else
        {
            $criteria['select'] = [ '*' ];
        }

        if ( null !== ( $value = $this->request->getPayloadData( 'params' ) ) )
        {
            $criteria['params'] = $value;
        }

        if ( null !== ( $value = $this->request->getParameter( 'filter' ) ) )
        {
            $criteria['condition'] = $value;

            //	Add current user ID into parameter array if in condition, but not specified.
            if ( false !== stripos( $value, ':user_id' ) )
            {
                if ( !isset( $criteria['params'][':user_id'] ) )
                {
                    $criteria['params'][':user_id'] = Session::getCurrentUserId();
                }
            }
        }

        $value = intval( $this->request->getParameter( 'limit' ) );
        $maxAllowed = intval( \Config::get( 'df.db_max_records_returned', self::MAX_RECORDS_RETURNED ) );
        if ( ( $value < 1 ) || ( $value > $maxAllowed ) )
        {
            // impose a limit to protect server
            $value = $maxAllowed;
        }
        $criteria['limit'] = $value;

        if ( null !== ( $value = $this->request->getParameter( 'offset' ) ) )
        {
            $criteria['offset'] = $value;
        }

        if ( null !== ( $value = $this->request->getParameter( 'order' ) ) )
        {
            $criteria['order'] = $value;
        }

        return $criteria;
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $ids = $this->request->getParameter( 'ids' );
        $records = $this->getPayloadData( self::RECORD_WRAPPER );

        $data = null;

        $related = $this->request->getParameter( 'related' );
        if ( !empty( $related ) )
        {
            $related = explode( ',', $related );
        }
        else
        {
            $related = [ ];
        }

        //	Single resource by ID
        if ( !empty( $this->resource ) )
        {
            $data = $this->retrieveById( $this->resource, $related );
        }
        else if ( !empty( $ids ) )
        {
            $data = $this->retrieveByIds( $ids, $related );
        }
        else if ( !empty( $records ) )
        {
            $data = $this->retrieveByRecords( $records, $related );
        }
        else
        {
            $data = $this->retrieveByRequest( $related );
        }

        if ( empty( $data ) )
        {
            throw new NotFoundException( "Record not found." );
        }

        if ( $this->request->getParameterAsBool( 'include_count' ) === true )
        {
            if ( isset( $data['record'] ) )
            {
                $data['meta']['count'] = count( $data['record'] );
            }
            elseif ( !empty( $data ) )
            {
                $data['meta']['count'] = 1;
            }
        }

        if ( !empty( $data ) && $this->request->getParameterAsBool( 'include_schema' ) === true )
        {
            /** @var BaseSystemModel $model */
            $model = $this->getModel();
            $data['meta']['schema'] = $model->getTableSchema()->toArray();
        }

        return ResponseFactory::create( $data, $this->nativeFormat );
    }

    /**
     * Creates new records in bulk.
     *
     * @param array $records
     * @param array $params
     *
     * @return mixed
     */
    protected function bulkCreate( array $records, array $params = [ ] )
    {
        /** @var BaseSystemModel $model */
        $modelClass = $this->model;
        $result = $modelClass::bulkCreate( $records, $params );

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
        if ( !empty( $this->resource ) )
        {
            throw new BadRequestException( 'Create record by identifier not currently supported.' );
        }

        $records = $this->getPayloadData( self::RECORD_WRAPPER );

        if ( empty( $records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        $result = $this->bulkCreate( $records, $this->request->getParameters() );

        $response = ResponseFactory::create( $result, $this->nativeFormat, ServiceResponseInterface::HTTP_CREATED );

        return $response;
    }

    /**
     * @throws BadRequestException
     */
    protected function handlePUT()
    {
        throw new BadRequestException( 'PUT is not supported on System Resource. Use PATCH' );
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
    protected function updateById( $id, array $record, array $params = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $result = $modelClass::updateById( $id, $record, $params );

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
    protected function updateByIds( $ids, array $record, array $params = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $result = $modelClass::updateByIds( $ids, $record, $params );

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
    protected function bulkUpdate( array $records, array $params = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $result = $modelClass::bulkUpdate( $records, $params );

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
        $records = $this->getPayloadData( static::RECORD_WRAPPER );
        $ids = $this->request->getParameter( 'ids' );

        if ( empty( $records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        if ( !empty( $this->resource ) )
        {
            $result = $this->updateById( $this->resource, $records[0], $this->request->getParameters() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $this->updateByIds( $ids, $records[0], $this->request->getParameters() );
        }
        else
        {
            $result = $this->bulkUpdate( $records, $this->request->getParameters() );
        }

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
    protected function deleteById( $id, array $params = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $result = $modelClass::deleteById( $id, $params );

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
    protected function deleteByIds( $ids, array $params = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $result = $modelClass::deleteByIds( $ids, $params );

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
    protected function bulkDelete( array $records, array $params = [ ] )
    {
        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->model;
        $result = $modelClass::bulkDelete( $records, $params );

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
        $this->triggerActionEvent( $this->response );
        $ids = $this->request->getParameter( 'ids' );

        if ( !empty( $this->resource ) )
        {
            $result = $this->deleteById( $this->resource, $this->request->getParameters() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $this->deleteByIds( $ids, $this->request->getParameters() );
        }
        else
        {
            $records = $this->getPayloadData( static::RECORD_WRAPPER );

            if ( empty( $records ) )
            {
                throw new BadRequestException( 'No record(s) detected in request.' );
            }

            $result = $this->bulkDelete( $records, $this->request->getParameters() );
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
        if ( empty( $this->model ) || !class_exists( $this->model ) )
        {
            throw new ModelNotFoundException();
        }

        return new $this->model;
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName( '.' );
        $name = Inflector::camelize( $this->name );
        $plural = Inflector::pluralize( $name );
        $words = str_replace( '_', ' ', $this->name );
        $pluralWords = Inflector::pluralize( $words );
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $plural . '() - Retrieve one or more ' . $pluralWords . '.',
                        'nickname'         => 'get' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
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
                        'summary'          => 'create' . $plural . '() - Create one or more ' . $pluralWords . '.',
                        'nickname'         => 'create' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => $eventPath . '.create',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to create.',
                                'allowMultiple' => false,
                                'type'          => $plural . 'Request',
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
                                'enum'          => [ 'GET', 'PUT', 'PATCH', 'DELETE' ],
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
                        'summary'          => 'update' . $plural . '() - Update one or more ' . $pluralWords . '.',
                        'nickname'         => 'update' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => $eventPath . '.update',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to update.',
                                'allowMultiple' => false,
                                'type'          => $plural . 'Request',
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
                        'summary'          => 'delete' . $plural . '() - Delete one or more ' . $pluralWords . '.',
                        'nickname'         => 'delete' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => $eventPath . '.delete',
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
                'description' => "Operations for $words administration.",
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
                        'summary'          => 'update' . $name . '() - Update one ' . $words . '.',
                        'nickname'         => 'update' . $name,
                        'type'             => $name . 'Response',
                        'event_name'       => $eventPath . '.update',
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
                                'type'          => $name . 'Request',
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
                        'summary'          => 'delete' . $name . '() - Delete one ' . $words . '.',
                        'nickname'         => 'delete' . $name,
                        'type'             => $name . 'Response',
                        'event_name'       => $eventPath . '.delete',
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
                'description' => "Operations for individual $words administration.",
            ],
        ];

        $models = [
            $plural . 'Request'  => [
                'id'         => $plural . 'Request',
                'properties' => [
                    'record' => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => $name . 'Request',
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
            $plural . 'Response' => [
                'id'         => $plural . 'Response',
                'properties' => [
                    'record' => [
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
                        'type'        => 'Array',
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
        if ( $model )
        {
            $temp = $model->toApiDocsModel( $name );
            if ( $temp )
            {
                $models = array_merge( $models, $temp );
            }
        }

        return [ 'apis' => $apis, 'models' => $models ];
    }
}