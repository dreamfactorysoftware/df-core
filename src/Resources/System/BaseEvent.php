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
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\EventScript as EventScriptModel;
use DreamFactory\Core\Models\BaseSystemModel;
use DreamFactory\Core\Utility\ResponseFactory;
use Illuminate\Database\Eloquent\Collection;

/**
 * Class BaseEvent
 *
 * @package DreamFactory\Core\Resources
 */
abstract class BaseEvent extends BaseSystemResource
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var BaseSystemModel $modelClass
     */
    protected $model = 'DreamFactory\Core\Models\EventScript';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    abstract protected function getEventMap();

    /**
     * Handles GET action
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        $results = $this->getEventMap();

        if ( empty( $this->resource ) )
        {
            $scripts = EventScriptModel::where( 'affects_process', 1 )->lists( 'name' );

            $allEvents = [ ];
            foreach ( $results as $service => &$apis )
            {
                foreach ( $apis as $path => &$operations )
                {
                    foreach ( $operations as $method => &$events )
                    {
                        $temp = [ ];
                        foreach ( $events as $event )
                        {
                            $temp[$event] = count( array_keys( $scripts, $event ) );
                            $allEvents[] = $event;
                        }
                        $events = $temp;
                    }
                }
            }

            if ( $this->request->getParameterAsBool( 'full_map', false ) )
            {
                return $results;
            }

            return [ 'resource' => $allEvents ];
        }

//        if ( empty( $this->resourceId ) )
//        {
//            $scripts = EventScriptModel::where( 'name', $this->resource )->get();
//            if ( empty( $scripts ) )
//            {
//                throw new NotFoundException( "Event {$this->resource} not found in the system." );
//            }
//
//            return [ 'record' => $scripts ];
//        }
//
//        $script = EventScriptModel::where( 'id', $this->resourceId )->first();
//        if ( empty( $script ) )
//        {
//            throw new NotFoundException( "Event {$this->resource} not found in the system." );
//        }

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

        $modelClass = $this->model;
        $model = $this->getModel();
        $pk = $model->getPrimaryKey();

        //	Single resource by ID
        if ( !empty( $this->resourceId ) )
        {
            $foundModel = $modelClass::with( $related )->find( $this->resourceId );
            if ( $foundModel )
            {
                $data = $foundModel->toArray();
            }
        }
        else if ( !empty( $ids ) )
        {
            /** @var Collection $dataCol */
            $dataCol = $modelClass::with( $related )->where( 'name', $this->resource )->whereIn( $pk, explode( ',', $ids ) )->get();
            $data = $dataCol->toArray();
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

            /** @var Collection $dataCol */
            $dataCol = $modelClass::with( $related )->where( 'name', $this->resource )->whereIn( $pk, $ids )->get();
            $data = $dataCol->toArray();
            $data = [ self::RECORD_WRAPPER => $data ];
        }
        else
        {
            //	Build our criteria
            $criteria = [
                'params' => [ ],
            ];

            if ( null !== ( $value = $this->request->getParameter( 'fields' ) ) )
            {
                $criteria['select'] = $value;
            }
            else
            {
                $criteria['select'] = "*";
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
                        //$criteria['params'][':user_id'] = Session::getCurrentUserId();
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

            $_fields = [ '*' ];
            if ( !empty( $criteria['select'] ) )
            {
                $_fields = explode( ',', $criteria['select'] );
            }

            if ( empty( $criteria ) )
            {
                $collections = $modelClass::where( 'name', $this->resource )->get( $_fields );
            }
            else
            {
                $collections = $modelClass::with( $related )->where( 'name', $this->resource )->get( $_fields );
            }

            $data = $collections->toArray();

            $data = [ static::RECORD_WRAPPER => $data ];
        }

        if ( null === $data )
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
            $data['meta']['schema'] = $model->getTableSchema()->toArray();
        }

        return ResponseFactory::create( $data, $this->nativeFormat );
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
        if ( empty( $this->resource ) )
        {
            return false;
        }

        if ( !empty( $this->resourceId ) )
        {
            throw new BadRequestException( 'Create record by identifier not currently supported.' );
        }

        $records = $this->getPayloadData( self::RECORD_WRAPPER );

        if ( empty( $records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        $modelClass = $this->model;
        $result = $modelClass::bulkCreate( $records, $this->request->getParameters() );

        $response = ResponseFactory::create( $result, $this->nativeFormat, ServiceResponseInterface::HTTP_CREATED );

        return $response;
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
        if ( empty( $this->resource ) )
        {
            return false;
        }

        $records = $this->getPayloadData( static::RECORD_WRAPPER );
        $ids = $this->request->getParameter( 'ids' );
        $modelClass = $this->model;

        if ( empty( $records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        if ( !empty( $this->resourceId ) )
        {
            $result = $modelClass::updateById( $this->resourceId, $records[0], $this->request->getParameters() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $modelClass::updateByIds( $ids, $records[0], $this->request->getParameters() );
        }
        else
        {
            $result = $modelClass::bulkUpdate( $records, $this->request->getParameters() );
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
        if ( empty( $this->resource ) )
        {
            return false;
        }

        $this->triggerActionEvent( $this->response );
        $ids = $this->request->getParameter( 'ids' );
        $modelClass = $this->model;

        if ( !empty( $this->resourceId ) )
        {
            $result = $modelClass::deleteById( $this->resource, $this->request->getParameters() );
        }
        elseif ( !empty( $ids ) )
        {
            $result = $modelClass::deleteByIds( $ids, $this->request->getParameters() );
        }
        else
        {
            $records = $this->getPayloadData( static::RECORD_WRAPPER );

            if ( empty( $records ) )
            {
                throw new BadRequestException( 'No record(s) detected in request.' );
            }
            $result = $modelClass::bulkDelete( $records, $this->request->getParameters() );
        }

        return $result;
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName( '.' );
        $name = Inflector::camelize( $this->name );
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . 'Events() - Retrieve list of events.',
                        'nickname'         => 'get' . $name . 'Events',
                        'type'             => 'ComponentList',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'parameters'       => [ ],
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
                        'notes'            => 'A list of event names are returned. <br>',
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . 'EventMap() - Retrieve full map of events.',
                        'nickname'         => 'get' . $name . 'EventMap',
                        'type'             => 'EventMap',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'parameters'       => [
                            [
                                'name'          => 'full_map',
                                'description'   => 'Get the full mapping of events.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => true,
                                'default'       => true,
                            ]
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
                        'notes'            => 'This returns a service to verb to event mapping. <br>',
                    ],
                ],
                'description' => 'Operations for retrieving events.',
            ],
            [
                'path'        => $path . '/{event_name}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . 'EventScripts() - Retrieve scripts for one event.',
                        'nickname'         => 'get' . $name . 'EventScripts',
                        'type'             => 'EventScriptsResponse',
                        'event_name'       => $eventPath . '.{event_name}.read',
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
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
                        'summary'          => 'create' . $name . 'EventScripts() - Create one or more event scripts.',
                        'nickname'         => 'create' . $name . 'EventScripts',
                        'type'             => 'EventScriptsResponse',
                        'event_name'       => $eventPath . '.{event_name}.create',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to create.',
                                'allowMultiple' => false,
                                'type'          => 'EventScriptsRequest',
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
                        'summary'          => 'update' . $name . 'EventScripts() - Update one or more event scripts.',
                        'nickname'         => 'update' . $name . 'EventScripts',
                        'type'             => 'EventScriptsResponse',
                        'event_name'       => $eventPath . '.{event_name}.update',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of records to update.',
                                'allowMultiple' => false,
                                'type'          => 'EventScriptsRequest',
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
                        'summary'          => 'delete' . $name . 'EventScripts() - Delete one or more event scripts.',
                        'nickname'         => 'delete' . $name . 'EventScripts',
                        'type'             => 'EventScriptsResponse',
                        'event_name'       => $eventPath . '.{event_name}.delete',
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
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
                'description' => 'Operations for scripts on individual events.',
            ],
            [
                'path'        => $path . '/{event_name}/{id}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . 'EventScript() - Retrieve one script.',
                        'nickname'         => 'get' . $name . 'EventScript',
                        'type'             => 'EventScriptResponse',
                        'event_name'       => $eventPath . '.{event_name}.{id}.read',
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
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
                        'summary'          => 'update' . $name . 'EventScript() - Update one script.',
                        'nickname'         => 'update' . $name . 'EventScript',
                        'type'             => 'EventScriptResponse',
                        'event_name'       => $eventPath . '.{event_name}.{id}.update',
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
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
                                'type'          => 'EventScriptRequest',
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
                        'summary'          => 'delete' . $name . 'EventScript() - Delete one script.',
                        'nickname'         => 'delete' . $name . 'EventScript',
                        'type'             => 'EventScriptResponse',
                        'event_name'       => $eventPath . '.{event_name}.{id}.delete',
                        'parameters'       => [
                            [
                                'name'          => 'event_name',
                                'description'   => 'Identifier of the event to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
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
                'description' => 'Operations for individual event scripts.',
            ],
        ];

        $models = [
            'EventScriptsRequest'  => [
                'id'         => 'EventScriptsRequest',
                'properties' => [
                    'record' => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => 'EventScriptRequest',
                        ],
                    ],
                    'ids'    => [
                        'type'        => 'array',
                        'description' => 'Array of event identifiers, used for batch GET.',
                        'items'       => [
                            'type'   => 'integer',
                            'format' => 'int32',
                        ],
                    ],
                ],
            ],
            'EventScriptsResponse' => [
                'id'         => 'EventScriptsResponse',
                'properties' => [
                    'record' => [
                        'type'        => 'array',
                        'description' => 'Array of system records.',
                        'items'       => [
                            '$ref' => 'EventScriptResponse',
                        ],
                    ],
                    'meta'   => [
                        'type'        => 'Metadata',
                        'description' => 'Array of metadata returned for GET requests.',
                    ],
                ],
            ],
        ];

        $model = $this->getModel();
        if ( $model )
        {
            $temp = $model->toApiDocsModel( 'EventScript' );
            if ( $temp )
            {
                $models = array_merge( $models, $temp );
            }
        }

        return [ 'apis' => $apis, 'models' => $models ];
    }
}