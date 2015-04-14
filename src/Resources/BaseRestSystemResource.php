<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
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

namespace DreamFactory\Rave\Resources;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use Illuminate\Support\Facades\Config;
use DreamFactory\Rave\Utility\ResponseFactory;
use DreamFactory\Rave\Models\BaseSystemModel;

/**
 * Class BaseRestSystemResource
 *
 * @package DreamFactory\Rave\Resources
 */
class BaseRestSystemResource extends BaseRestResource
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
     * @var \DreamFactory\Rave\Models\BaseSystemModel Model Class name.
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
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $requestQuery = $this->getQueryData();
        $ids = ArrayUtils::get( $requestQuery, 'ids' );
        $records = $this->getPayloadData( self::RECORD_WRAPPER );

        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->getModel();
        /** @var BaseSystemModel $model */
        $model = new $modelClass;

        $data = null;

        //	Single resource by ID
        if ( !empty( $this->resource ) )
        {
            $dataCol = $modelClass::find( $this->resource );
            if ( $dataCol )
            {
                $data = $dataCol->toArray();
            }
        }
        else if ( !empty( $ids ) )
        {
            $dataCol = $modelClass::whereIn( 'id', explode( ',', $ids ) )->get();
            $data = $dataCol->all();
            $data = [ self::RECORD_WRAPPER => $data ];
        }
        else if ( !empty( $records ) )
        {
            $pk = $model->getPrimaryKey();
            $ids = [ ];

            foreach ( $records as $record )
            {
                $ids[] = ArrayUtils::get( $record, $pk );
            }

            $dataCol = $model->whereIn( 'id', $ids )->get();
            $data = $dataCol->all();
            $data = [ self::RECORD_WRAPPER => $data ];
        }
        else
        {
            //	Build our criteria
            $criteria = [
                'params' => [ ],
            ];

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'fields' ) ) )
            {
                $criteria['select'] = $value;
            }
            else
            {
                $criteria['select'] = "*";
            }

            if ( null !== ( $value = $this->getPayloadData( 'params' ) ) )
            {
                $criteria['params'] = $value;
            }

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'filter' ) ) )
            {
                $criteria['condition'] = $value;

                //	Add current user ID into parameter array if in condition, but not specified.
                if ( false !== stripos( $value, ':user_id' ) )
                {
                    if ( !isset( $criteria['params'][':user_id'] ) )
                    {
                        //$criteria['params'][':user_id'] = Session::getCurrentUserId();
                    }
                }
            }

            $value = intval( ArrayUtils::get( $requestQuery, 'limit' ) );
            $maxAllowed = intval( Config::get( 'rave.db_max_records_returned', self::MAX_RECORDS_RETURNED ) );
            if ( ( $value < 1 ) || ( $value > $maxAllowed ) )
            {
                // impose a limit to protect server
                $value = $maxAllowed;
            }
            $criteria['limit'] = $value;

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'offset' ) ) )
            {
                $criteria['offset'] = $value;
            }

            if ( null !== ( $value = ArrayUtils::get( $requestQuery, 'order' ) ) )
            {
                $criteria['order'] = $value;
            }

            $data = $model->selectResponse( $criteria );
        }

        if ( null === $data )
        {
            throw new NotFoundException( "Record not found." );
        }

        return $data;
    }

    /**
     * Handles POST action
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePost()
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

        $model = $this->getModel();
        $result = $model::bulkCreate( $records, $this->getQueryData() );

        $response = ResponseFactory::create( $result, $this->outputFormat, ServiceResponseInterface::HTTP_CREATED );

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
     * Handles PATCH action
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePATCH()
    {
        $records = $this->getPayloadData( static::RECORD_WRAPPER );
        $ids = $this->getQueryData( 'ids' );
        $modelClass = $this->getModel();

        if ( empty( $records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        if ( !empty( $this->resource ) )
        {
            $result = $modelClass::updateById( $this->resource, $records[0], $this->getQueryData() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $modelClass::updateByIds( $ids, $records[0], $this->getQueryData() );
        }
        else
        {
            $result = $modelClass::bulkUpdate( $records, $this->getQueryData() );
        }

        return $result;
    }

    /**
     * Handles DELETE action
     *
     * @return \DreamFactory\Rave\Utility\ServiceResponse
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handleDELETE()
    {
        $this->triggerActionEvent( $this->response );
        $ids = $this->getQueryData( 'ids' );
        $modelClass = $this->getModel();

        if ( !empty( $this->resource ) )
        {
            $result = $modelClass::deleteById( $this->resource, $this->getQueryData() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $modelClass::deleteByIds( $ids, $this->getQueryData() );
        }
        else
        {
            $records = $this->getPayloadData( static::RECORD_WRAPPER );

            if ( empty( $records ) )
            {
                throw new BadRequestException( 'No record(s) detected in request.' );
            }
            $result = $modelClass::bulkDelete( $records, $this->getQueryData() );
        }

        return $result;
    }

    /**
     * Returns associated model with the service/resource.
     *
     * @return \DreamFactory\Rave\Models\BaseSystemModel
     * @throws ModelNotFoundException
     */
    protected function getModel()
    {
        if ( empty( $this->model ) )
        {
            throw new ModelNotFoundException();
        }

        return $this->model;
    }

    public function getApiDocInfo()
    {
        $name = Inflector::camelize( $this->name );
        $lower = Inflector::camelize( $this->name, null, false, true );
        $plural = Inflector::pluralize( $name );
        $pluralLower = Inflector::pluralize( $lower );
        $apis = [
            [
                'path'        => '/{api_name}/' . $this->name,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $plural . '() - Retrieve one or more ' . $pluralLower . '.',
                        'nickname'         => 'get' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => '{api_name}.' . $pluralLower . '.list',
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
                        'summary'          => 'create' . $plural . '() - Create one or more ' . $pluralLower . '.',
                        'nickname'         => 'create' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => '{api_name}.' . $pluralLower . '.create',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
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
                        'summary'          => 'update' . $plural . '() - Update one or more ' . $pluralLower . '.',
                        'nickname'         => 'update' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => '{api_name}.' . $pluralLower . '.update',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
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
                        'summary'          => 'delete' . $plural . '() - Delete one or more ' . $pluralLower . '.',
                        'nickname'         => 'delete' . $plural,
                        'type'             => $plural . 'Response',
                        'event_name'       => '{api_name}.' . $pluralLower . '.delete',
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
                'path'        => '/{api_name}/' . $lower . '/{id}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . '() - Retrieve one ' . $lower . '.',
                        'nickname'         => 'get' . $name,
                        'type'             => $name . 'Response',
                        'event_name'       => '{api_name}.' . $lower . '.read',
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
                        'summary'          => 'update' . $name . '() - Update one ' . $lower . '.',
                        'nickname'         => 'update' . $name,
                        'type'             => $name . 'Response',
                        'event_name'       => '{api_name}.' . $lower . '.update',
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
                        'summary'          => 'delete' . $name . '() - Delete one ' . $lower . '.',
                        'nickname'         => 'delete' . $name,
                        'type'             => $name . 'Response',
                        'event_name'       => '{api_name}.' . $lower . '.delete',
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
            'Related' . $plural  => [
                'id'         => 'Related' . $plural,
                'properties' => [
                    'record' => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => 'Related' . $name,
                        ],
                    ],
                    'meta'   => [
                        'type'        => 'Metadata',
                        'description' => 'Array of metadata returned for GET requests.',
                    ],
                ],
            ],
        ];

        return [ 'apis' => $apis, 'models' => $models ];
    }
}