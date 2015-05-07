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

namespace DreamFactory\Rave\Resources\System;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Resources\BaseRestResource;
use DreamFactory\Rave\Models\BaseSystemModel;
use DreamFactory\Rave\Utility\ResponseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ScriptType extends BaseRestResource
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
        $this->model = new \DreamFactory\Rave\Models\ScriptType();
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

        /** @var BaseSystemModel $modelClass */
        $modelClass = $this->getModel();
        /** @var BaseSystemModel $model */
        $model = new $modelClass;
        $pk = $model->getPrimaryKey();

        //	Single resource by ID
        if ( !empty( $this->resource ) )
        {
            $foundModel = $modelClass::with( $related )->find( $this->resource );
            if ( $foundModel )
            {
                $data = $foundModel->toArray();
            }
        }
        else if ( !empty( $ids ) )
        {
            /** @var Collection $dataCol */
            $dataCol = $modelClass::with( $related )->whereIn( $pk, explode( ',', $ids ) )->get();
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
            $dataCol = $modelClass::with( $related )->whereIn( $pk, $ids )->get();
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
            $maxAllowed = intval( \Config::get( 'rave.db_max_records_returned', self::MAX_RECORDS_RETURNED ) );
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

            $data = $model->selectResponse( $criteria, $related );
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

        if ( empty( $data ) )
        {
            return ResponseFactory::create( $data, $this->outputFormat, ServiceResponseInterface::HTTP_NOT_FOUND );
        }

        return ResponseFactory::create( $data, $this->outputFormat, ServiceResponseInterface::HTTP_OK );
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
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $name = Inflector::camelize( $this->name );
        $lower = Inflector::camelize( $this->name, null, false, true );
        $plural = Inflector::pluralize( $name );
        $pluralLower = Inflector::pluralize( $lower );
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $plural . '() - Retrieve one or more ' . $pluralLower . '.',
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
                ],
                'description' => "Operations for $lower administration.",
            ],
            [
                'path'        => $path . '/{id}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'get' . $name . '() - Retrieve one ' . $lower . '.',
                        'nickname'         => 'get' . $name,
                        'type'             => $name,
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
                ],
                'description' => "Operations for individual $lower administration.",
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
                            '$ref' => $name,
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
                            '$ref' => $name,
                        ],
                    ],
                    'meta'   => [
                        'type'        => 'Metadata',
                        'description' => 'Array of metadata returned for GET requests.',
                    ],
                ],
            ],
        ];

        if ( $this->model )
        {
            $temp = $this->model->toApiDocsModel($name);
            if ($temp)
            {
                $models = array_merge($models, $temp);
            }
        }

        return [ 'apis' => $apis, 'models' => $models ];
    }
}