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

use Config;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Utility\ApiDocUtilities;
use DreamFactory\Rave\Utility\DbUtilities;
use DreamFactory\Rave\Enums\DbFilterOperators;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\ForbiddenException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\NotImplementedException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\RestException;

abstract class BaseDbTableResource extends BaseDbResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Resource tag for dealing with table schema
     */
    const RESOURCE_NAME = '_table';
    /**
     * Default maximum records returned on filter request
     */
    const MAX_RECORDS_RETURNED = 1000;
    /**
     * Default record wrapping tag for single or array of records
     */
    const RECORD_WRAPPER = 'record';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array
     */
    protected $payload = [ ];
    /**
     * @var array
     */
    protected $options = [ ];
    /**
     * @var array
     */
    protected $ids = [ ];
    /**
     * @var boolean
     */
    protected $_useBlendFormat = true;
    /**
     * @var string
     */
    protected $_transactionTable = null;
    /**
     * @var array
     */
    protected $_batchIds = [ ];
    /**
     * @var array
     */
    protected $_batchRecords = [ ];
    /**
     * @var array
     */
    protected $_rollbackRecords = [ ];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@InheritDoc}
     */
    protected function setResourceMembers( $response_path = null )
    {
        $out = parent::setResourceMembers( $response_path );

        $this->detectRequestMembers();

        return $out;
    }

    /**
     *
     *
     * IMPORTANT: The representation of the data will be placed back into the original location/position in the $record from which it was "normalized".
     * This means that any client-side handlers will have to deal with the bogus determinations. Just be aware.
     *
     * Below is a side-by-side comparison of record data as shown sent by or returned to the caller, and sent to an event handler.
     *
     *  REST API v1.0                           Event Representation
     *  -------------                           --------------------
     *  Single row...                           Add a 'record' key and make it look like a multi-row
     *
     *      array(                              array(
     *          'id' => 1,                          'record' => array(
     *      )                                           0 => array( 'id' => 1, ),
     *                                              ),
     *                                          ),
     *
     * Multi-row...                             Stays the same...or gets wrapped by adding a 'record' key
     *
     *      array(                              array(
     *          'record' => array(                  'record' =>  array(
     *              0 => array( 'id' => 1 ),            0 => array( 'id' => 1 ),
     *              1 => array( 'id' => 2 ),            1 => array( 'id' => 2 ),
     *              2 => array( 'id' => 3 ),            2 => array( 'id' => 3 ),
     *          ),                                  ),
     *      )                                   )
     *
     * or...
     *
     *      array(                              array(
     *          0 => array( 'id' => 1 ),            'record' =>  array(
     *          1 => array( 'id' => 2 ),                0 => array( 'id' => 1 ),
     *          2 => array( 'id' => 3 ),                1 => array( 'id' => 2 ),
     *      ),                                          2 => array( 'id' => 3 ),
     *                                              ),
     *                                          )
     */
    protected function detectRequestMembers()
    {
        // override - don't call parent class here
        $this->payload = $this->getPayloadData();

        if ( !empty( $this->resource ) )
        {
            if ( !empty( $this->resourceId ) )
            {
                if ( !empty( $this->payload ) )
                {
                    // fix wrapper on posted single record
                    if ( !isset( $this->payload[static::RECORD_WRAPPER] ) )
                    {
                        // single records don't use the record wrapper, so wrap it
                        $this->payload = [ static::RECORD_WRAPPER => [ $this->payload ] ];
                    }
                }
            }
            elseif ( ArrayUtils::isArrayNumeric( $this->payload ) )
            {
                // import from csv, etc doesn't include a wrapper, so wrap it
                $this->payload = [ static::RECORD_WRAPPER => $this->payload ];
            }
            else
            {
                if ( !empty( $this->payload ) )
                {
                    switch ( $this->request->getMethod() )
                    {
                        case Verbs::POST:
                        case Verbs::PUT:
                        case Verbs::PATCH:
                        case Verbs::MERGE:
                            // fix wrapper on posted single record
                            if ( !isset( $this->payload[static::RECORD_WRAPPER] ) )
                            {
                                // stuff it back in for event
                                $this->payload[static::RECORD_WRAPPER] = [ $this->payload ];
                            }
                            break;
                    }
                }
            }

            $this->options = $this->request->getParameters();

            // merge in possible payload options
            $optionNames = [ 'limit', 'offset', 'order', 'fields', 'ids', 'filter', 'params', 'continue', 'rollback' ];
            $otherNames = [ 'top', 'skip', 'sort', null, null, null, null, null, null ]; // Microsoft et al preferences

            foreach ( $optionNames as $key => $value )
            {
                if ( !array_key_exists( $value, $this->options ) )
                {
                    if ( array_key_exists( $value, $this->payload ) )
                    {
                        $this->options[$value] = $this->payload[$value];
                    }
                    elseif ( isset( $otherNames[$key] ) )
                    {
                        $other = $otherNames[$key];
                        if ( !array_key_exists( $other, $this->options ) )
                        {
                            if ( array_key_exists( $other, $this->payload ) )
                            {
                                $this->options[$value] = $this->payload[$other];
                            }
                        }
                        else
                        {
                            $this->options[$value] = $this->options[$other];
                        }
                    }
                }
            }

            // set defaults if not present
            if ( Verbs::GET == $this->request->getMethod() )
            {
                // default for GET should be "return all fields"
                if ( !array_key_exists( 'fields', $this->options ) )
                {
                    $this->options['fields'] = '*';
                }
            }

            // Add server side filtering properties
//        if ( null != $_ssFilters = Session::getServiceFilters( $this->getRequestedAction(), $this->name, $this->resource ) )
//        {
//            $this->payload['ss_filters'] = $_ssFilters;
//        }
        }
    }

    /**
     * @param string $name
     *
     * @throws NotFoundException
     * @throws BadRequestException
     */
    public function correctTableName( &$name )
    {
    }

    /**
     * @param string $table
     * @param string $action
     *
     * @throws BadRequestException
     */
    protected function validateTableAccess( $table = null, $action = null )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        $resource = static::RESOURCE_NAME;
        $this->correctTableName( $table );

        // finally check that the current user has privileges to access this table
        $this->service->validateResourceAccess( $resource, $table, $action );
    }

    /**
     * @return array
     */
    protected function handleGet()
    {
        if ( empty( $this->resource ) )
        {
            return parent::handleGET();
        }

        if ( !empty( $this->resourceId ) )
        {
            //	Single resource by ID
            $_result = $this->retrieveRecordById( $this->resource, $this->resourceId, $this->options );
            $this->triggerActionEvent( $_result );

            return $_result;
        }

        $_ids = ArrayUtils::get( $this->options, 'ids' );
        if ( !empty( $_ids ) )
        {
            //	Multiple resources by ID
            $_result = $this->retrieveRecordsByIds( $this->resource, $_ids, $this->options );
        }
        else
        {
            $_records = ArrayUtils::get( $this->payload, static::RECORD_WRAPPER );
            if ( !empty( $_records ) )
            {
                // passing records to have them updated with new or more values, id field required
                $_result = $this->retrieveRecords( $this->resource, $_records, $this->options );
            }
            else
            {
                $_filter = ArrayUtils::get( $this->options, 'filter' );
                $_params = ArrayUtils::get( $this->options, 'params', [ ] );

                $_result = $this->retrieveRecordsByFilter( $this->resource, $_filter, $_params, $this->options );
            }
        }

        $_meta = ArrayUtils::get( $_result, 'meta' );
        unset( $_result['meta'] );
        $_result = [ static::RECORD_WRAPPER => $_result ];

        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        $this->triggerActionEvent( $_result );

        return $_result;
    }

    /**
     * @return array
     * @throws BadRequestException
     */
    protected function handlePost()
    {
        if ( empty( $this->resource ) )
        {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        if ( !empty( $this->resourceId ) )
        {
            throw new BadRequestException( 'Create record by identifier not currently supported.' );
        }

        $_records = ArrayUtils::get( $this->payload, static::RECORD_WRAPPER );
        if ( empty( $_records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        $_result = $this->createRecords( $this->resource, $_records, $this->options );

        $_meta = ArrayUtils::get( $_result, 'meta' );
        unset( $_result['meta'] );
        $_result = [ static::RECORD_WRAPPER => $_result ];
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws BadRequestException
     */
    protected function handlePUT()
    {
        if ( empty( $this->resource ) )
        {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        $_records = ArrayUtils::get( $this->payload, static::RECORD_WRAPPER );
        if ( empty( $_records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        if ( !empty( $this->resourceId ) )
        {
            $_record = ArrayUtils::get( $_records, 0, $_records );

            return $this->updateRecordById( $this->resource, $_record, $this->resourceId, $this->options );
        }

        $_ids = ArrayUtils::get( $this->options, 'ids' );

        if ( !empty( $_ids ) )
        {
            $_record = ArrayUtils::get( $_records, 0, $_records );

            $_result = $this->updateRecordsByIds( $this->resource, $_record, $_ids, $this->options );
        }
        else
        {
            $_filter = ArrayUtils::get( $this->options, 'filter' );
            if ( !empty( $_filter ) )
            {
                $_record = ArrayUtils::get( $_records, 0, $_records );
                $_params = ArrayUtils::get( $this->options, 'params', [ ] );
                $_result = $this->updateRecordsByFilter(
                    $this->resource,
                    $_record,
                    $_filter,
                    $_params,
                    $this->options
                );
            }
            else
            {
                $_result = $this->updateRecords( $this->resource, $_records, $this->options );
            }
        }

        $_meta = ArrayUtils::get( $_result, 'meta' );
        unset( $_result['meta'] );
        $_result = [ static::RECORD_WRAPPER => $_result ];
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws BadRequestException
     */
    protected function handlePatch()
    {
        if ( empty( $this->resource ) )
        {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        $_records = ArrayUtils::get( $this->payload, static::RECORD_WRAPPER );
        if ( empty( $_records ) )
        {
            throw new BadRequestException( 'No record(s) detected in request.' );
        }

        $this->triggerActionEvent( $this->response );

        if ( !empty( $this->resourceId ) )
        {
            $_record = ArrayUtils::get( $_records, 0, $_records );

            return $this->patchRecordById( $this->resource, $_record, $this->resourceId, $this->options );
        }

        $_ids = ArrayUtils::get( $this->options, 'ids' );

        if ( !empty( $_ids ) )
        {
            $_record = ArrayUtils::get( $_records, 0, $_records );
            $_result = $this->patchRecordsByIds( $this->resource, $_record, $_ids, $this->options );
        }
        else
        {
            $_filter = ArrayUtils::get( $this->options, 'filter' );
            if ( !empty( $_filter ) )
            {
                $_record = ArrayUtils::get( $_records, 0, $_records );
                $_params = ArrayUtils::get( $this->options, 'params', [ ] );
                $_result = $this->patchRecordsByFilter(
                    $this->resource,
                    $_record,
                    $_filter,
                    $_params,
                    $this->options
                );
            }
            else
            {
                $_result = $this->patchRecords( $this->resource, $_records, $this->options );
            }
        }

        $_meta = ArrayUtils::get( $_result, 'meta' );
        unset( $_result['meta'] );
        $_result = [ static::RECORD_WRAPPER => $_result ];
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }

    /**
     * @return array
     * @throws BadRequestException
     */
    protected function handleDelete()
    {
        if ( empty( $this->resource ) )
        {
            // not currently supported, maybe batch opportunity?
            return false;
        }

        $this->triggerActionEvent( $this->response );

        if ( !empty( $this->resourceId ) )
        {
            return $this->deleteRecordById( $this->resource, $this->resourceId, $this->options );
        }

        $_ids = ArrayUtils::get( $this->options, 'ids' );
        if ( !empty( $_ids ) )
        {
            $_result = $this->deleteRecordsByIds( $this->resource, $_ids, $this->options );
        }
        else
        {
            $_records = ArrayUtils::get( $this->payload, static::RECORD_WRAPPER );
            if ( !empty( $_records ) )
            {
                $_result = $this->deleteRecords( $this->resource, $_records, $this->options );
            }
            else
            {
                $_filter = ArrayUtils::get( $this->options, 'filter' );
                if ( !empty( $_filter ) )
                {
                    $_params = ArrayUtils::get( $this->options, 'params', [ ] );
                    $_result = $this->deleteRecordsByFilter( $this->resource, $_filter, $_params, $this->options );
                }
                else
                {
                    if ( !ArrayUtils::getBool( $this->options, 'force' ) )
                    {
                        throw new BadRequestException( 'No filter or records given for delete request.' );
                    }

                    return $this->truncateTable( $this->resource, $this->options );
                }
            }
        }

        $_meta = ArrayUtils::get( $_result, 'meta' );
        unset( $_result['meta'] );
        $_result = [ static::RECORD_WRAPPER => $_result ];
        if ( !empty( $_meta ) )
        {
            $_result['meta'] = $_meta;
        }

        return $_result;
    }

    // Handle table record operations

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecords( $table, $records, $extras = [ ] )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_isSingle = ( 1 == count( $records ) );
        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_rollback = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = [ ];
        $_errors = [ ];
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                // operation performed, take output, override earlier
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = [ 'error' => $_errors, static::RECORD_WRAPPER => $_out ];
                $_msg = 'Batch Error: Not all records could be created.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext( $_context );
                $_ex->setMessage( $_msg );
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to create records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecord( $table, $record, $extras = [ ] )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->createRecords( $table, $_records, $extras );

        return $_results[0];
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecords( $table, $records, $extras = [ ] )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $records ) );
        $_rollback = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = [ ];
        $_errors = [ ];
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = [ 'error' => $_errors, static::RECORD_WRAPPER => $_out ];
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext( $_context );
                $_ex->setMessage( $_msg );
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecord( $table, $record, $extras = [ ] )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->updateRecords( $table, $_records, $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = [ ], $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than merging individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo );
        $extras['fields'] = $_fields;

        return $this->updateRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws InternalServerErrorException
     * @throws BadRequestException
     * @throws RestException
     * @return array
     */
    public function updateRecordsByIds( $table, $record, $ids, $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        static::removeIds( $record, $_idFields );
        $extras['updates'] = $record;

        $_out = [ ];
        $_errors = [ ];
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = [ 'error' => $_errors, static::RECORD_WRAPPER => $_out ];
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext( $_context );
                $_ex->setMessage( $_msg );
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordById( $table, $record, $id, $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->updateRecordsByIds( $table, $record, $id, $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecords( $table, $records, $extras = [ ] )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $records ) );
        $_rollback = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = [ ];
        $_errors = [ ];
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = [ 'error' => $_errors, static::RECORD_WRAPPER => $_out ];
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext( $_context );
                $_ex->setMessage( $_msg );
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecord( $table, $record, $extras = [ ] )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->patchRecords( $table, $_records, $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * @param string $table
     * @param  array $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordsByFilter( $table, $record, $filter = null, $params = [ ], $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than merging individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo );
        $extras['fields'] = $_fields;

        return $this->patchRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordsByIds( $table, $record, $ids, $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        static::removeIds( $record, $_idFields );
        $extras['updates'] = $record;

        $_out = [ ];
        $_errors = [ ];
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = [ 'error' => $_errors, static::RECORD_WRAPPER => $_out ];
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext( $_context );
                $_ex->setMessage( $_msg );
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecordById( $table, $record, $id, $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->patchRecordsByIds( $table, $record, $id, $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecords( $table, $records, $extras = [ ] )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = [ ];
        foreach ( $records as $_record )
        {
            $_ids[] = static::checkForIds( $_record, $_idsInfo, $extras );
        }

        return $this->deleteRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecord( $table, $record, $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->deleteRecords( $table, [ $record ], $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByFilter( $table, $filter, $params = [ ], $extras = [ ] )
    {
        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than deleting individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo, $extras );
        $extras['fields'] = $_fields;

        return $this->deleteRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByIds( $table, $ids, $extras = [ ] )
    {
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = [ ];
        $_errors = [ ];
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = [ 'error' => $_errors, static::RECORD_WRAPPER => $_out ];
                $_msg = 'Batch Error: Not all records could be deleted.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext( $_context );
                $_ex->setMessage( $_msg );
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordById( $table, $id, $extras = [ ] )
    {
        $_results = $this->deleteRecordsByIds( $table, $id, $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * Delete all table entries but keep the table
     *
     * @param string $table
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function truncateTable( $table, $extras = [ ] )
    {
        // todo faster way?
        $_records = $this->retrieveRecordsByFilter( $table, null, null, $extras );

        if ( !empty( $_records ) )
        {
            $this->deleteRecords( $table, $_records, $extras );
        }

        return [ 'success' => true ];
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function retrieveRecordsByFilter( $table, $filter = null, $params = [ ], $extras = [ ] );

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecords( $table, $records, $extras = [ ] )
    {
        $records = DbUtilities::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = [ ];
        foreach ( $records as $_record )
        {
            $_ids[] = static::checkForIds( $_record, $_idsInfo, $extras );
        }

        return $this->retrieveRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecord( $table, $record, $extras = [ ] )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->retrieveRecords( $table, [ $record ], $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = [ ] )
    {
        $ids = DbUtilities::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = ArrayUtils::get( $extras, 'fields' );
        $_idFields = ArrayUtils::get( $extras, 'id_field' );
        $_idTypes = ArrayUtils::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_continue = ( $_isSingle ) ? false : ArrayUtils::getBool( $extras, 'continue', false );

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['single'] = $_isSingle;
        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = [ ];
        $_errors = [ ];
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, false, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[$_index] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[$_index] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[$_index] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = [ 'error' => $_errors, static::RECORD_WRAPPER => $_out ];
                $_msg = 'Batch Error: Not all records could be retrieved.';
            }

            if ( $_ex instanceof RestException )
            {
                $_temp = $_ex->getContext();
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext( $_context );
                $_ex->setMessage( $_msg );
                throw $_ex;
            }

            throw new InternalServerErrorException( "Failed to retrieve records from '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param mixed  $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordById( $table, $id, $extras = [ ] )
    {
        $_results = $this->retrieveRecordsByIds( $table, $id, $extras );

        return ArrayUtils::get( $_results, 0, [ ] );
    }

    /**
     * @param mixed $handle
     *
     * @return bool
     */
    protected function initTransaction( $handle = null )
    {
        $this->_transactionTable = $handle;
        $this->_batchRecords = [ ];
        $this->_batchIds = [ ];
        $this->_rollbackRecords = [ ];

        return true;
    }

    /**
     * @param mixed      $record
     * @param mixed      $id
     * @param null|array $extras Additional items needed to complete the transaction
     * @param bool       $rollback
     * @param bool       $continue
     * @param bool       $single
     *
     * @throws NotImplementedException
     * @return null|array Array of output fields
     */
    protected function addToTransaction( $record = null, $id = null, /** @noinspection PhpUnusedParameterInspection */
        $extras = null, /** @noinspection PhpUnusedParameterInspection */
        $rollback = false, /** @noinspection PhpUnusedParameterInspection */
        $continue = false, /** @noinspection PhpUnusedParameterInspection */
        $single = false )
    {
        if ( !empty( $record ) )
        {
            $this->_batchRecords[] = $record;
        }
        if ( !empty( $id ) )
        {
            $this->_batchIds[] = $id;
        }

        return null;
    }

    /**
     * @param null|array $extras Additional items needed to complete the transaction
     *
     * @throws NotFoundException
     * @throws BadRequestException
     * @return array Array of output records
     */
    abstract protected function commitTransaction( $extras = null );

    /**
     * @param mixed $record
     *
     * @return bool
     */
    protected function addToRollback( $record )
    {
        if ( !empty( $record ) )
        {
            $this->_rollbackRecords[] = $record;
        }

        return true;
    }

    /**
     * @return bool
     */
    abstract protected function rollbackTransaction();

    // Helper function for record usage

    /**
     * @param $table
     *
     * @return array
     * @throws BadRequestException
     */
    protected function getFieldsInfo( $table )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table can not be empty.' );
        }

        return [ ];
    }

    /**
     * @param      $table
     * @param null $fields_info
     * @param null $requested_fields
     * @param null $requested_types
     *
     * @return mixed
     */
    abstract protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null );

    /**
     * @param $id_info
     *
     * @return array
     */
    protected function getIdFieldsFromInfo( $id_info )
    {
        $_fields = [ ];
        foreach ( $id_info as $_info )
        {
            $_fields[] = ArrayUtils::get( $_info, 'name' );
        }

        return $_fields;
    }

    /**
     * @param      $record
     * @param      $ids_info
     * @param null $extras
     * @param bool $on_create
     * @param bool $remove
     *
     * @return array|bool|int|mixed|null|string
     */
    protected function checkForIds( &$record, $ids_info, $extras = null, $on_create = false, $remove = false )
    {
        $_id = null;
        if ( !empty( $ids_info ) )
        {
            if ( 1 == count( $ids_info ) )
            {
                $_info = $ids_info[0];
                $_name = ArrayUtils::get( $_info, 'name' );
                if ( is_array( $record ) )
                {
                    $_value = ArrayUtils::get( $record, $_name );
                    if ( $remove )
                    {
                        unset( $record[$_name] );
                    }
                }
                else
                {
                    $_value = $record;
                }
                if ( !empty( $_value ) )
                {
                    $_type = ArrayUtils::get( $_info, 'type' );
                    switch ( $_type )
                    {
                        case 'int':
                            $_value = intval( $_value );
                            break;
                        case 'string':
                            $_value = strval( $_value );
                            break;
                    }
                    $_id = $_value;
                }
                else
                {
                    $_required = ArrayUtils::getBool( $_info, 'required' );
                    // could be passed in as a parameter affecting all records
                    $_param = ArrayUtils::get( $extras, $_name );
                    if ( $on_create && $_required && empty( $_param ) )
                    {
                        return false;
                    }
                }
            }
            else
            {
                $_id = [ ];
                foreach ( $ids_info as $_info )
                {
                    $_name = ArrayUtils::get( $_info, 'name' );
                    if ( is_array( $record ) )
                    {
                        $_value = ArrayUtils::get( $record, $_name );
                        if ( $remove )
                        {
                            unset( $record[$_name] );
                        }
                    }
                    else
                    {
                        $_value = $record;
                    }
                    if ( !empty( $_value ) )
                    {
                        $_type = ArrayUtils::get( $_info, 'type' );
                        switch ( $_type )
                        {
                            case 'int':
                                $_value = intval( $_value );
                                break;
                            case 'string':
                                $_value = strval( $_value );
                                break;
                        }
                        $_id[$_name] = $_value;
                    }
                    else
                    {
                        $_required = ArrayUtils::getBool( $_info, 'required' );
                        // could be passed in as a parameter affecting all records
                        $_param = ArrayUtils::get( $extras, $_name );
                        if ( $on_create && $_required && empty( $_param ) )
                        {
                            return false;
                        }
                    }
                }
            }
        }

        if ( !empty( $_id ) )
        {
            return $_id;
        }
        elseif ( $on_create )
        {
            return [ ];
        }

        return false;
    }

    /**
     * @param array $record
     * @param array $fields_info
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRecord( $record, $fields_info, $filter_info = null, $for_update = false, $old_record = null )
    {
//        $record = DataFormat::arrayKeyLower( $record );
        $_parsed = ( empty( $fields_info ) ) ? $record : [ ];
        if ( !empty( $fields_info ) )
        {
            $_keys = array_keys( $record );
            $_values = array_values( $record );
            foreach ( $fields_info as $_fieldInfo )
            {
//            $name = strtolower( ArrayUtils::get( $field_info, 'name', '' ) );
                $_name = ArrayUtils::get( $_fieldInfo, 'name', '' );
                $_type = ArrayUtils::get( $_fieldInfo, 'type' );
                $_pos = array_search( $_name, $_keys );
                if ( false !== $_pos )
                {
                    $_fieldVal = ArrayUtils::get( $_values, $_pos );
                    // due to conversion from XML to array, null or empty xml elements have the array value of an empty array
                    if ( is_array( $_fieldVal ) && empty( $_fieldVal ) )
                    {
                        $_fieldVal = null;
                    }

                    /** validations **/

                    $_validations = ArrayUtils::get( $_fieldInfo, 'validation' );

                    if ( !static::validateFieldValue( $_name, $_fieldVal, $_validations, $for_update, $_fieldInfo ) )
                    {
                        unset( $_keys[$_pos] );
                        unset( $_values[$_pos] );
                        continue;
                    }

                    $_parsed[$_name] = $_fieldVal;
                    unset( $_keys[$_pos] );
                    unset( $_values[$_pos] );
                }

                // add or override for specific fields
                switch ( $_type )
                {
                    case 'timestamp_on_create':
                        if ( !$for_update )
                        {
                            $_parsed[$_name] = time();
                        }
                        break;
                    case 'timestamp_on_update':
                        $_parsed[$_name] = time();
                        break;
                    case 'user_id_on_create':
                        if ( !$for_update )
                        {
                            $userId = 1; // TODO Session::getCurrentUserId();
                            if ( isset( $userId ) )
                            {
                                $_parsed[$_name] = $userId;
                            }
                        }
                        break;
                    case 'user_id_on_update':
                        $userId = 1; // TODO Session::getCurrentUserId();
                        if ( isset( $userId ) )
                        {
                            $_parsed[$_name] = $userId;
                        }
                        break;
                }
            }
        }

        if ( !empty( $filter_info ) )
        {
            $this->validateRecord( $_parsed, $filter_info, $for_update, $old_record );
        }

        return $_parsed;
    }

    /**
     * @param array $record
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @throws \Exception
     */
    protected function validateRecord( $record, $filter_info, $for_update = false, $old_record = null )
    {
        $_filters = ArrayUtils::get( $filter_info, 'filters' );

        if ( empty( $_filters ) || empty( $record ) )
        {
            return;
        }

        $_combiner = ArrayUtils::get( $filter_info, 'filter_op', 'and' );
        foreach ( $_filters as $_filter )
        {
            $_filterField = ArrayUtils::get( $_filter, 'name' );
            $_operator = ArrayUtils::get( $_filter, 'operator' );
            $_filterValue = ArrayUtils::get( $_filter, 'value' );
            $_filterValue = static::interpretFilterValue( $_filterValue );
            $_foundInRecord = ( is_array( $record ) ) ? array_key_exists( $_filterField, $record ) : false;
            $_recordValue = ArrayUtils::get( $record, $_filterField );
            $_foundInOld = ( is_array( $old_record ) ) ? array_key_exists( $_filterField, $old_record ) : false;
            $_oldValue = ArrayUtils::get( $old_record, $_filterField );
            $_compareFound = ( $_foundInRecord || ( $for_update && $_foundInOld ) );
            $_compareValue = $_foundInRecord ? $_recordValue : ( $for_update ? $_oldValue : null );

            $_reason = null;
            if ( $for_update && !$_compareFound )
            {
                // not being set, filter on update will check old record
                continue;
            }

            if ( !static::compareByOperator( $_operator, $_compareFound, $_compareValue, $_filterValue ) )
            {
                $_reason = "Denied access to some of the requested fields.";
            }

            switch ( strtolower( $_combiner ) )
            {
                case 'and':
                    if ( !empty( $_reason ) )
                    {
                        // any reason is a good reason to bail
                        throw new ForbiddenException( $_reason );
                    }
                    break;
                case 'or':
                    if ( empty( $_reason ) )
                    {
                        // at least one was successful
                        return;
                    }
                    break;
                default:
                    throw new InternalServerErrorException( 'Invalid server configuration detected.' );

            }
        }
    }

    /**
     * @param $operator
     * @param $left_found
     * @param $left
     * @param $right
     *
     * @return bool
     * @throws InternalServerErrorException
     */
    public static function compareByOperator( $operator, $left_found, $left, $right )
    {
        switch ( $operator )
        {
            case DbFilterOperators::EQ:
                return ( $left == $right );
            case DbFilterOperators::NE:
                return ( $left != $right );
            case DbFilterOperators::GT:
                return ( $left > $right );
            case DbFilterOperators::LT:
                return ( $left < $right );
            case DbFilterOperators::GE:
                return ( $left >= $right );
            case DbFilterOperators::LE:
                return ( $left <= $right );
            case DbFilterOperators::STARTS_WITH:
                return static::startsWith( $left, $right );
            case DbFilterOperators::ENDS_WITH:
                return static::endswith( $left, $right );
            case DbFilterOperators::CONTAINS:
                return ( false !== strpos( $left, $right ) );
            case DbFilterOperators::IN:
                return ArrayUtils::isInList( $right, $left );
            case DbFilterOperators::NOT_IN:
                return !ArrayUtils::isInList( $right, $left );
            case DbFilterOperators::IS_NULL:
                return is_null( $left );
            case DbFilterOperators::IS_NOT_NULL:
                return !is_null( $left );
            case DbFilterOperators::DOES_EXIST:
                return ( $left_found );
            case DbFilterOperators::DOES_NOT_EXIST:
                return ( !$left_found );
            default:
                throw new InternalServerErrorException( 'Invalid server configuration detected.' );
        }
    }

    /**
     * @param      $name
     * @param      $value
     * @param      $validations
     * @param bool $for_update
     * @param null $field_info
     *
     * @return bool
     * @throws InternalServerErrorException
     * @throws BadRequestException
     */
    protected static function validateFieldValue( $name, $value, $validations, $for_update = false, $field_info = null )
    {
        if ( is_array( $validations ) )
        {
            foreach ( $validations as $_key => $_config )
            {
                $_onFail = ArrayUtils::get( $_config, 'on_fail' );
                $_throw = true;
                $_msg = null;
                if ( !empty( $_onFail ) )
                {
                    if ( 0 == strcasecmp( $_onFail, 'ignore_field' ) )
                    {
                        $_throw = false;
                    }
                    else
                    {
                        $_msg = $_onFail;
                    }
                }

                switch ( $_key )
                {
                    case 'api_read_only':
                        if ( $_throw )
                        {
                            if ( empty( $_msg ) )
                            {
                                $_msg = "Field '$name' is read only.";
                            }
                            throw new BadRequestException( $_msg );
                        }

                        return false;
                        break;
                    case 'create_only':
                        if ( $for_update )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' can only be set during record creation.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_null':
                        if ( is_null( $value ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value can not be null.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_empty':
                        if ( !is_null( $value ) && empty( $value ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value can not be empty.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_zero':
                        if ( !is_null( $value ) && empty( $value ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value can not be empty.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'email':
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_EMAIL ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value must be a valid email address.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'url':
                        $_sections = ArrayUtils::clean( ArrayUtils::get( $_config, 'sections' ) );
                        $_flags = 0;
                        foreach ( $_sections as $_format )
                        {
                            switch ( strtolower( $_format ) )
                            {
                                case 'path':
                                    $_flags &= FILTER_FLAG_PATH_REQUIRED;
                                    break;
                                case 'query':
                                    $_flags &= FILTER_FLAG_QUERY_REQUIRED;
                                    break;
                            }
                        }
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_URL, $_flags ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value must be a valid URL.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'int':
                        $_min = ArrayUtils::getDeep( $_config, 'range', 'min' );
                        $_max = ArrayUtils::getDeep( $_config, 'range', 'max' );
                        $_formats = ArrayUtils::clean( ArrayUtils::get( $_config, 'formats' ) );

                        $_options = [ ];
                        if ( is_int( $_min ) )
                        {
                            $_options['min_range'] = $_min;
                        }
                        if ( is_int( $_max ) )
                        {
                            $_options['max_range'] = $_max;
                        }
                        $_flags = 0;
                        foreach ( $_formats as $_format )
                        {
                            switch ( strtolower( $_format ) )
                            {
                                case 'hex':
                                    $_flags &= FILTER_FLAG_ALLOW_HEX;
                                    break;
                                case 'octal':
                                    $_flags &= FILTER_FLAG_ALLOW_OCTAL;
                                    break;
                            }
                        }
                        $_options = [ 'options' => $_options, 'flags' => $_flags ];
                        if ( !is_null( $value ) && false === filter_var( $value, FILTER_VALIDATE_INT, $_options ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is not in the valid range.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'float':
                        $_decimal = ArrayUtils::get( $_config, 'decimal', '.' );
                        $_options['decimal'] = $_decimal;
                        $_options = [ 'options' => $_options ];
                        if ( !is_null( $value ) && !filter_var( $value, FILTER_VALIDATE_FLOAT, $_options ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is not an acceptable float value.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'boolean':
                        if ( !is_null( $value ) && !filter_var( $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is not an acceptable boolean value.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'match':
                        $_regex = ArrayUtils::get( $_config, 'regexp' );
                        if ( empty( $_regex ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'regexp'." );
                        }

                        $_regex = base64_decode( $_regex );
                        $_options = [ 'regexp' => $_regex ];
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_REGEXP, $_options ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is invalid.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'picklist':
                        $_values = ArrayUtils::get( $field_info, 'value' );
                        if ( empty( $_values ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'value' in schema settings." );
                        }

                        if ( !empty( $value ) && ( false === array_search( $value, $_values ) ) )
                        {
                            if ( $_throw )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value is invalid.";
                                }
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'multi_picklist':
                        $_values = ArrayUtils::get( $field_info, 'value' );
                        if ( empty( $_values ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'value' in schema settings." );
                        }

                        if ( !empty( $value ) )
                        {
                            $_delimiter = ArrayUtils::get( $_config, 'delimiter', ',' );
                            $_min = ArrayUtils::get( $_config, 'min', 1 );
                            $_max = ArrayUtils::get( $_config, 'max' );
                            $value = DbUtilities::validateAsArray( $value, $_delimiter, true );
                            $_count = count( $value );
                            if ( $_count < $_min )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value does not contain enough selections.";
                                }
                                throw new BadRequestException( $_msg );
                            }
                            if ( !empty( $_max ) && ( $_count > $_max ) )
                            {
                                if ( empty( $_msg ) )
                                {
                                    $_msg = "Field '$name' value contains too many selections.";
                                }
                                throw new BadRequestException( $_msg );
                            }
                            foreach ( $value as $_item )
                            {
                                if ( false === array_search( $_item, $_values ) )
                                {
                                    if ( $_throw )
                                    {
                                        if ( empty( $_msg ) )
                                        {
                                            $_msg = "Field '$name' value is invalid.";
                                        }
                                        throw new BadRequestException( $_msg );
                                    }

                                    return false;
                                }
                            }
                        }
                        break;
                }
            }
        }

        return true;
    }

    /**
     * @return int
     */
    protected static function getMaxRecordsReturnedLimit()
    {
        return intval( Config::get( 'rave.db_max_records_returned', static::MAX_RECORDS_RETURNED ) );
    }

    /**
     * @param array        $record
     * @param string|array $include  List of keys to include in the output record
     * @param string|array $id_field Single or list of identifier fields
     *
     * @return array
     */
    protected static function cleanRecord( $record = [ ], $include = '*', $id_field = null )
    {
        if ( '*' !== $include )
        {
            if ( !empty( $id_field ) && !is_array( $id_field ) )
            {
                $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
            }
            $id_field = ArrayUtils::clean( $id_field );

            if ( !empty( $include ) && !is_array( $include ) )
            {
                $include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
            }
            $include = ArrayUtils::clean( $include );

            // make sure we always include identifier fields
            foreach ( $id_field as $id )
            {
                if ( false === array_search( $id, $include ) )
                {
                    $include[] = $id;
                }
            }

            // glean desired fields from record
            $_out = [ ];
            foreach ( $include as $_key )
            {
                $_out[$_key] = ArrayUtils::get( $record, $_key );
            }

            return $_out;
        }

        return $record;
    }

    /**
     * @param array $records
     * @param mixed $include
     * @param mixed $id_field
     *
     * @return array
     */
    protected static function cleanRecords( $records, $include = '*', $id_field = null )
    {
        $_out = [ ];
        foreach ( $records as $_record )
        {
            $_out[] = static::cleanRecord( $_record, $include, $id_field );
        }

        return $_out;
    }

    /**
     * @param array $records
     * @param       $ids_info
     * @param null  $extras
     * @param bool  $on_create
     * @param bool  $remove
     *
     * @internal param string $id_field
     * @internal param bool $include_field
     *
     * @return array
     */
    protected static function recordsAsIds( $records, $ids_info, $extras = null, $on_create = false, $remove = false )
    {
        $_out = [ ];
        if ( !empty( $records ) )
        {
            foreach ( $records as $_record )
            {
                $_out[] = static::checkForIds( $_record, $ids_info, $extras, $on_create, $remove );
            }
        }

        return $_out;
    }

    /**
     * @param array  $record
     * @param string $id_field
     * @param bool   $include_field
     * @param bool   $remove
     *
     * @throws BadRequestException
     * @return array
     */
    protected static function recordAsId( &$record, $id_field = null, $include_field = false, $remove = false )
    {
        if ( empty( $id_field ) )
        {
            return [ ];
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        if ( count( $id_field ) > 1 )
        {
            $_ids = [ ];
            foreach ( $id_field as $_field )
            {
                $_id = ArrayUtils::get( $record, $_field );
                if ( $remove )
                {
                    unset( $record[$_field] );
                }
                if ( empty( $_id ) )
                {
                    throw new BadRequestException( "Identifying field '$_field' can not be empty for record." );
                }
                $_ids[$_field] = $_id;
            }

            return $_ids;
        }
        else
        {
            $_field = $id_field[0];
            $_id = ArrayUtils::get( $record, $_field );
            if ( $remove )
            {
                unset( $record[$_field] );
            }
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '$_field' can not be empty for record." );
            }

            return ( $include_field ) ? [ $_field => $_id ] : $_id;
        }
    }

    /**
     * @param        $ids
     * @param string $id_field
     * @param bool   $field_included
     *
     * @return array
     */
    protected static function idsAsRecords( $ids, $id_field, $field_included = false )
    {
        if ( empty( $id_field ) )
        {
            return [ ];
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        $_out = [ ];
        foreach ( $ids as $_id )
        {
            $_ids = [ ];
            if ( ( count( $id_field ) > 1 ) && ( count( $_id ) > 1 ) )
            {
                foreach ( $id_field as $_index => $_field )
                {
                    $_search = ( $field_included ) ? $_field : $_index;
                    $_ids[$_field] = ArrayUtils::get( $_id, $_search );
                }
            }
            else
            {
                $_field = $id_field[0];
                $_ids[$_field] = $_id;
            }

            $_out[] = $_ids;
        }

        return $_out;
    }

    /**
     * @param array $record
     * @param array $id_field
     */
    protected static function removeIds( &$record, $id_field )
    {
        if ( !empty( $id_field ) )
        {

            if ( !is_array( $id_field ) )
            {
                $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
            }

            foreach ( $id_field as $_name )
            {
                unset( $record[$_name] );
            }
        }
    }

    /**
     * @param      $record
     * @param null $id_field
     *
     * @return bool
     */
    protected static function _containsIdFields( $record, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            return false;
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        foreach ( $id_field as $_field )
        {
            $_temp = ArrayUtils::get( $record, $_field );
            if ( empty( $_temp ) )
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @param        $fields
     * @param string $id_field
     *
     * @return bool
     */
    protected static function _requireMoreFields( $fields, $id_field = null )
    {
        if ( ( '*' == $fields ) || empty( $id_field ) )
        {
            return true;
        }

        if ( false === $fields = DbUtilities::validateAsArray( $fields, ',' ) )
        {
            return false;
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        foreach ( $id_field as $_key => $_name )
        {
            if ( false !== array_search( $_name, $fields ) )
            {
                unset( $fields[$_key] );
            }
        }

        return !empty( $fields );
    }

    /**
     * @param        $first_array
     * @param        $second_array
     * @param string $id_field
     *
     * @return mixed
     */
    protected static function recordArrayMerge( $first_array, $second_array, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            return [ ];
        }

        foreach ( $first_array as $_key => $_first )
        {
            $_firstId = ArrayUtils::get( $_first, $id_field );
            foreach ( $second_array as $_second )
            {
                $_secondId = ArrayUtils::get( $_second, $id_field );
                if ( $_firstId == $_secondId )
                {
                    $first_array[$_key] = array_merge( $_first, $_second );
                }
            }
        }

        return $first_array;
    }

    /**
     * @param $value
     *
     * @return bool|int|null|string
     */
    public static function interpretFilterValue( $value )
    {
        // all other data types besides strings, just return
        if ( !is_string( $value ) || empty( $value ) )
        {
            return $value;
        }

        $_end = strlen( $value ) - 1;
        // filter string values should be wrapped in matching quotes
        if ( ( ( 0 === strpos( $value, '"' ) ) && ( $_end === strrpos( $value, '"' ) ) ) ||
             ( ( 0 === strpos( $value, "'" ) ) && ( $_end === strrpos( $value, "'" ) ) )
        )
        {
            return substr( $value, 1, $_end - 1 );
        }

        // check for boolean or null values
        switch ( strtolower( $value ) )
        {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        if ( is_numeric( $value ) )
        {
            return $value + 0; // trick to get int or float
        }

        // the rest should be lookup keys, or plain strings
//        Session::replaceLookups( $value );

        return $value;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public static function interpretRecordValues( $record )
    {
        if ( !is_array( $record ) || empty( $record ) )
        {
            return $record;
        }

        foreach ( $record as $_field => $_value )
        {
//            Session::replaceLookups( $_value );
            $record[$_field] = $_value;
        }

        return $record;
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function startsWith( $haystack, $needle )
    {
        return ( substr( $haystack, 0, strlen( $needle ) ) === $needle );
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function endsWith( $haystack, $needle )
    {
        return ( substr( $haystack, -strlen( $needle ) ) === $needle );
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $_base = parent::getApiDocInfo();

        $_commonResponses = ApiDocUtilities::getCommonResponses();

        $_apis = [
            [
                'path'        => $path,
                'description' => 'Operations available for SQL DB Tables.',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getTables() - List resources available for database tables.',
                        'nickname'         => 'getTables',
                        'type'             => 'Resources',
                        'event_name'       => $eventPath . '.list',
                        'parameters'       => [
                            [
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the schema list.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'See listed operations for each resource available.',
                    ],
                ],
            ],
            [
                'path'        => $path . '/{table_name}',
                'description' => 'Operations for table records administration.',
                'operations'  =>
                    [
                        [
                            'method'           => 'GET',
                            'summary'          => 'getRecordsByFilter() - Retrieve one or more records by using a filter.',
                            'nickname'         => 'getRecordsByFilter',
                            'notes'            =>
                                'Set the <b>filter</b> parameter to a SQL WHERE clause (optional native filter accepted in some scenarios) ' .
                                'to limit records returned or leave it blank to return all records up to the maximum limit.<br/> ' .
                                'Set the <b>limit</b> parameter with or without a filter to return a specific amount of records.<br/> ' .
                                'Use the <b>offset</b> parameter along with the <b>limit</b> parameter to page through sets of records.<br/> ' .
                                'Set the <b>order</b> parameter to SQL ORDER_BY clause containing field and optional direction (<field_name> [ASC|DESC]) to order the returned records.<br/> ' .
                                'Alternatively, to send the <b>filter</b> with or without <b>params</b> as posted data, ' .
                                'use the getRecordsByPost() POST request and post a filter with or without params.<br/>' .
                                'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                                'By default, all fields are returned for all records. ',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.select', $eventPath . '.table_selected', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'filter',
                                        'description'   => 'SQL WHERE clause filter to limit the records retrieved.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'limit',
                                        'description'   => 'Maximum number of records to return.',
                                        'allowMultiple' => false,
                                        'type'          => 'integer',
                                        'format'        => 'int32',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'offset',
                                        'description'   => 'Offset the filter results to a particular record index (may require <b>order</b>> parameter in some scenarios).',
                                        'allowMultiple' => false,
                                        'type'          => 'integer',
                                        'format'        => 'int32',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'order',
                                        'description'   => 'SQL ORDER_BY clause containing field and direction for filter results.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'include_count',
                                        'description'   => 'Include the total number of filter results as meta data.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'include_schema',
                                        'description'   => 'Include table properties, including indexes and field details where available, as meta data.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'GET',
                            'summary'          => 'getRecordsByIds() - Retrieve one or more records by identifiers.',
                            'nickname'         => 'getRecordsByIds',
                            'notes'            =>
                                'Pass the identifying field values as a comma-separated list in the <b>ids</b> parameter.<br/> ' .
                                'Use the <b>id_field</b> and <b>id_type</b> parameters to override or specify detail for identifying fields where applicable.<br/> ' .
                                'Alternatively, to send the <b>ids</b> as posted data, use the getRecordsByPost() POST request.<br/> ' .
                                'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                                'By default, all fields are returned for identified records. ',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.select', $eventPath . '.table_selected', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
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
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'POST',
                            'summary'          => 'getRecordsByPost() - Retrieve one or more records by posting necessary data.',
                            'nickname'         => 'getRecordsByPost',
                            'notes'            =>
                                'Post data should be an array of records wrapped in a <b>record</b> element - including the identifying fields at a minimum, ' .
                                'or a <b>filter</b> in the SQL or other appropriate formats with or without a replacement <b>params</b> array, ' .
                                'or a list of <b>ids</b> in a string list or an array.<br/> ' .
                                'Use the <b>fields</b> parameter to limit properties returned for each record. ' .
                                'By default, all fields are returned for identified records. ',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.select', $eventPath . '.table_selected', ],
                            'parameters'       => [
                                [
                                    'name'          => 'table_name',
                                    'description'   => 'Name of the table to perform operations on.',
                                    'allowMultiple' => false,
                                    'type'          => 'string',
                                    'paramType'     => 'path',
                                    'required'      => true,
                                ],
                                [
                                    'name'          => 'body',
                                    'description'   => 'Data containing name-value pairs of records to retrieve.',
                                    'allowMultiple' => false,
                                    'type'          => 'GetRecordsRequest',
                                    'paramType'     => 'body',
                                    'required'      => true,
                                ],
                                [
                                    'name'          => 'fields',
                                    'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_field',
                                    'description'   =>
                                        'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_type',
                                    'description'   =>
                                        'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'continue',
                                    'description'   =>
                                        'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                        'Default behavior is to halt and return results up to the first point of failure.',
                                    'allowMultiple' => false,
                                    'type'          => 'boolean',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'X-HTTP-METHOD',
                                    'description'   => 'Override request using POST to tunnel other http request, such as GET.',
                                    'enum'          => [ 'GET' ],
                                    'allowMultiple' => false,
                                    'type'          => 'string',
                                    'paramType'     => 'header',
                                    'required'      => true,
                                ],
                            ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'GET',
                            'summary'          => 'getRecords() - Retrieve one or more records.',
                            'nickname'         => 'getRecords',
                            'notes'            => 'Here for SDK backwards compatibility, see getRecordsByFilter(), getRecordsByIds(), and getRecordsByPost()',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.select', $eventPath . '.table_selected', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
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
                                        'name'          => 'offset',
                                        'description'   => 'Set to offset the filter results to a particular record count.',
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
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'include_count',
                                        'description'   => 'Include the total number of filter results as meta data.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'include_schema',
                                        'description'   => 'Include the table schema as meta data.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'POST',
                            'summary'          => 'createRecords() - Create one or more records.',
                            'nickname'         => 'createRecords',
                            'notes'            =>
                                'Posted data should be an array of records wrapped in a <b>record</b> element.<br/> ' .
                                'By default, only the id property of the record is returned on success. ' .
                                'Use <b>fields</b> parameter to return more info.',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.insert', $eventPath . '.table_inserted', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing name-value pairs of records to create.',
                                        'allowMultiple' => false,
                                        'type'          => 'RecordsRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'rollback',
                                        'description'   =>
                                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
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
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PUT',
                            'summary'          => 'replaceRecordsByIds() - Update (replace) one or more records.',
                            'nickname'         => 'replaceRecordsByIds',
                            'notes'            =>
                                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                                'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                                'By default, only the id property of the record is returned on success. ' .
                                'Use <b>fields</b> parameter to return more info.',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing name-value pairs of records to update.',
                                        'allowMultiple' => false,
                                        'type'          => 'IdsRecordRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'ids',
                                        'description'   => 'Comma-delimited list of the identifiers of the records to modify.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'rollback',
                                        'description'   =>
                                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PUT',
                            'summary'          => 'replaceRecordsByFilter() - Update (replace) one or more records.',
                            'nickname'         => 'replaceRecordsByFilter',
                            'notes'            =>
                                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                                'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                                'By default, only the id property of the record is returned on success. ' .
                                'Use <b>fields</b> parameter to return more info.',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing name-value pairs of records to update.',
                                        'allowMultiple' => false,
                                        'type'          => 'FilterRecordRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'filter',
                                        'description'   => 'SQL-like filter to limit the records to modify.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PUT',
                            'summary'          => 'replaceRecords() - Update (replace) one or more records.',
                            'nickname'         => 'replaceRecords',
                            'notes'            =>
                                'Post data should be an array of records wrapped in a <b>record</b> tag.<br/> ' .
                                'By default, only the id property of the record is returned on success. ' .
                                'Use <b>fields</b> parameter to return more info.',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing name-value pairs of records to update.',
                                        'allowMultiple' => false,
                                        'type'          => 'RecordsRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'rollback',
                                        'description'   =>
                                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PATCH',
                            'summary'          => 'updateRecordsByIds() - Update (patch) one or more records.',
                            'nickname'         => 'updateRecordsByIds',
                            'notes'            =>
                                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                                'Ids can be included via URL parameter or included in the posted body.<br/> ' .
                                'By default, only the id property of the record is returned on success. ' .
                                'Use <b>fields</b> parameter to return more info.',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'A single record containing name-value pairs of fields to update.',
                                        'allowMultiple' => false,
                                        'type'          => 'IdsRecordRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'ids',
                                        'description'   => 'Comma-delimited list of the identifiers of the records to modify.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'rollback',
                                        'description'   =>
                                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PATCH',
                            'summary'          => 'updateRecordsByFilter() - Update (patch) one or more records.',
                            'nickname'         => 'updateRecordsByFilter',
                            'notes'            =>
                                'Posted body should be a single record with name-value pairs to update wrapped in a <b>record</b> tag.<br/> ' .
                                'Filter can be included via URL parameter or included in the posted body.<br/> ' .
                                'By default, only the id property of the record is returned on success. ' .
                                'Use <b>fields</b> parameter to return more info.',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing name-value pairs of fields to update.',
                                        'allowMultiple' => false,
                                        'type'          => 'FilterRecordRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'filter',
                                        'description'   => 'SQL-like filter to limit the records to modify.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PATCH',
                            'summary'          => 'updateRecords() - Update (patch) one or more records.',
                            'nickname'         => 'updateRecords',
                            'notes'            =>
                                'Post data should be an array of records containing at least the identifying fields for each record.<br/> ' .
                                'By default, only the id property of the record is returned on success. ' .
                                'Use <b>fields</b> parameter to return more info.',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing name-value pairs of records to update.',
                                        'allowMultiple' => false,
                                        'type'          => 'RecordsRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'rollback',
                                        'description'   =>
                                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'DELETE',
                            'summary'          => 'deleteRecordsByIds() - Delete one or more records.',
                            'nickname'         => 'deleteRecordsByIds',
                            'notes'            =>
                                'Set the <b>ids</b> parameter to a list of record identifying (primary key) values to delete specific records.<br/> ' .
                                'Alternatively, to delete records by a large list of ids, pass the ids in the <b>body</b>.<br/> ' .
                                'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.delete', $eventPath . '.table_deleted', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
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
                                        'name'          => 'body',
                                        'description'   => 'Data containing ids of records to delete.',
                                        'allowMultiple' => false,
                                        'type'          => 'IdsRequest',
                                        'paramType'     => 'body',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'rollback',
                                        'description'   =>
                                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'DELETE',
                            'summary'          => 'deleteRecordsByFilter() - Delete one or more records by using a filter.',
                            'nickname'         => 'deleteRecordsByFilter',
                            'notes'            =>
                                'Set the <b>filter</b> parameter to a SQL WHERE clause to delete specific records, ' .
                                'otherwise set <b>force</b> to true to clear the table.<br/> ' .
                                'Alternatively, to delete by a complicated filter or to use parameter replacement, pass the filter with or without params as the <b>body</b>.<br/> ' .
                                'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.delete', $eventPath . '.table_deleted', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'filter',
                                        'description'   => 'SQL WHERE clause filter to limit the records deleted.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing filter and/or params of records to delete.',
                                        'allowMultiple' => false,
                                        'type'          => 'FilterRequest',
                                        'paramType'     => 'body',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'force',
                                        'description'   => 'Set force to true to delete all records in this table, otherwise <b>filter</b> parameter is required.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                        'default'       => false,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'DELETE',
                            'summary'          => 'deleteRecords() - Delete one or more records.',
                            'nickname'         => 'deleteRecords',
                            'notes'            =>
                                'Set the <b>body</b> to an array of records, minimally including the identifying fields, to delete specific records.<br/> ' .
                                'By default, only the id property of the record is returned on success, use <b>fields</b> to return more info. ',
                            'type'             => 'RecordsResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.delete', $eventPath . '.table_deleted', ],
                            'parameters'       =>
                                [
                                    [
                                        'name'          => 'table_name',
                                        'description'   => 'Name of the table to perform operations on.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'path',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'body',
                                        'description'   => 'Data containing name-value pairs of records to delete.',
                                        'allowMultiple' => false,
                                        'type'          => 'RecordsRequest',
                                        'paramType'     => 'body',
                                        'required'      => true,
                                    ],
                                    [
                                        'name'          => 'fields',
                                        'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_field',
                                        'description'   =>
                                            'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'id_type',
                                        'description'   =>
                                            'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                            'used to override defaults or provide identifiers when none are provisioned.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'continue',
                                        'description'   =>
                                            'In batch scenarios, where supported, continue processing even after one record fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'rollback',
                                        'description'   =>
                                            'In batch scenarios, where supported, rollback all changes if any record of the batch fails. ' .
                                            'Default behavior is to halt and return results up to the first point of failure, leaving any changes.',
                                        'allowMultiple' => false,
                                        'type'          => 'boolean',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'filter',
                                        'description'   => 'For SDK backwards compatibility.',
                                        'allowMultiple' => false,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                    [
                                        'name'          => 'ids',
                                        'description'   => 'For SDK backwards compatibility.',
                                        'allowMultiple' => true,
                                        'type'          => 'string',
                                        'paramType'     => 'query',
                                        'required'      => false,
                                    ],
                                ],
                            'responseMessages' => $_commonResponses,
                        ],
                    ],
            ],
            [
                'path'        => $path . '/{table_name}/{id}',
                'description' => 'Operations for single record administration.',
                'operations'  =>
                    [
                        [
                            'method'           => 'GET',
                            'summary'          => 'getRecord() - Retrieve one record by identifier.',
                            'nickname'         => 'getRecord',
                            'notes'            =>
                                'Use the <b>fields</b> parameter to limit properties that are returned. ' .
                                'By default, all fields are returned.',
                            'type'             => 'RecordResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.select', $eventPath . '.table_selected', ],
                            'parameters'       => [
                                [
                                    'name'          => 'table_name',
                                    'description'   => 'Name of the table to perform operations on.',
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
                                    'description'   => 'Comma-delimited list of field names to retrieve for the record, \'*\' to return all fields.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_field',
                                    'description'   =>
                                        'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_type',
                                    'description'   =>
                                        'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                            ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'POST',
                            'summary'          => 'createRecord() - Create one record with given identifier.',
                            'nickname'         => 'createRecord',
                            'notes'            =>
                                'Post data should be an array of fields for a single record.<br/> ' .
                                'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
                            'type'             => 'RecordResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.create', $eventPath . '.table_created', ],
                            'parameters'       => [
                                [
                                    'name'          => 'table_name',
                                    'description'   => 'Name of the table to perform operations on.',
                                    'allowMultiple' => false,
                                    'type'          => 'string',
                                    'paramType'     => 'path',
                                    'required'      => true,
                                ],
                                [
                                    'name'          => 'id',
                                    'description'   => 'Identifier of the record to create.',
                                    'allowMultiple' => false,
                                    'type'          => 'string',
                                    'paramType'     => 'path',
                                    'required'      => true,
                                ],
                                [
                                    'name'          => 'body',
                                    'description'   => 'Data containing name-value pairs of the record to create.',
                                    'allowMultiple' => false,
                                    'type'          => 'RecordRequest',
                                    'paramType'     => 'body',
                                    'required'      => true,
                                ],
                                [
                                    'name'          => 'fields',
                                    'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_field',
                                    'description'   =>
                                        'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_type',
                                    'description'   =>
                                        'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                            ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PUT',
                            'summary'          => 'replaceRecord() - Replace the content of one record by identifier.',
                            'nickname'         => 'replaceRecord',
                            'notes'            =>
                                'Post data should be an array of fields for a single record.<br/> ' .
                                'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
                            'type'             => 'RecordResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       => [
                                [
                                    'name'          => 'table_name',
                                    'description'   => 'Name of the table to perform operations on.',
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
                                    'description'   => 'Data containing name-value pairs of the replacement record.',
                                    'allowMultiple' => false,
                                    'type'          => 'RecordRequest',
                                    'paramType'     => 'body',
                                    'required'      => true,
                                ],
                                [
                                    'name'          => 'fields',
                                    'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_field',
                                    'description'   =>
                                        'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_type',
                                    'description'   =>
                                        'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                            ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'PATCH',
                            'summary'          => 'updateRecord() - Update (patch) one record by identifier.',
                            'nickname'         => 'updateRecord',
                            'notes'            =>
                                'Post data should be an array of fields for a single record.<br/> ' .
                                'Use the <b>fields</b> parameter to return more properties. By default, the id is returned.',
                            'type'             => 'RecordResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.update', $eventPath . '.table_updated', ],
                            'parameters'       => [
                                [
                                    'name'          => 'table_name',
                                    'description'   => 'The name of the table you want to update.',
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
                                    'description'   => 'Data containing name-value pairs of the fields to update.',
                                    'allowMultiple' => false,
                                    'type'          => 'RecordRequest',
                                    'paramType'     => 'body',
                                    'required'      => true,
                                ],
                                [
                                    'name'          => 'fields',
                                    'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_field',
                                    'description'   =>
                                        'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_type',
                                    'description'   =>
                                        'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                            ],
                            'responseMessages' => $_commonResponses,
                        ],
                        [
                            'method'           => 'DELETE',
                            'summary'          => 'deleteRecord() - Delete one record by identifier.',
                            'nickname'         => 'deleteRecord',
                            'notes'            => 'Use the <b>fields</b> parameter to return more deleted properties. By default, the id is returned.',
                            'type'             => 'RecordResponse',
                            'event_name'       => [ $eventPath . '.{table_name}.delete', $eventPath . '.table_deleted', ],
                            'parameters'       => [
                                [
                                    'name'          => 'table_name',
                                    'description'   => 'Name of the table to perform operations on.',
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
                                    'description'   => 'Comma-delimited list of field names to retrieve for each record, \'*\' to return all fields.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_field',
                                    'description'   =>
                                        'Single or comma-delimited list of the fields used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                                [
                                    'name'          => 'id_type',
                                    'description'   =>
                                        'Single or comma-delimited list of the field types used as identifiers for the table, ' .
                                        'used to override defaults or provide identifiers when none are provisioned.',
                                    'allowMultiple' => true,
                                    'type'          => 'string',
                                    'paramType'     => 'query',
                                    'required'      => false,
                                ],
                            ],
                            'responseMessages' => $_commonResponses,
                        ],
                    ],
            ],
        ];

        $_commonProperties = [
            'id' => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Sample identifier of this record.',
            ],
        ];

        $_models = [
            'Tables'              => [
                'id'         => 'Tables',
                'properties' => [
                    'table' => [
                        'type'        => 'array',
                        'description' => 'Array of tables and their properties.',
                        'items'       => [
                            '$ref' => 'Table',
                        ],
                    ],
                ],
            ],
            'Table'               => [
                'id'         => 'Table',
                'properties' => [
                    'name' => [
                        'type'        => 'string',
                        'description' => 'Name of the table.',
                    ],
                ],
            ],
            'RecordRequest'       => [
                'id'         => 'RecordRequest',
                'properties' =>
                    $_commonProperties
            ],
            'RecordsRequest'      => [
                'id'         => 'RecordsRequest',
                'properties' => [
                    'record' => [
                        'type'        => 'array',
                        'description' => 'Array of records.',
                        'items'       => [
                            '$ref' => 'RecordRequest',
                        ],
                    ],
                ],
            ],
            'IdsRequest'          => [
                'id'         => 'IdsRequest',
                'properties' => [
                    'ids' => [
                        'type'        => 'array',
                        'description' => 'Array of record identifiers.',
                        'items'       => [
                            'type'   => 'integer',
                            'format' => 'int32',
                        ],
                    ],
                ],
            ],
            'IdsRecordRequest'    => [
                'id'         => 'IdsRecordRequest',
                'properties' => [
                    'record' => [
                        'type'        => 'RecordRequest',
                        'description' => 'A single record, array of fields, used to modify existing records.',
                    ],
                    'ids'    => [
                        'type'        => 'array',
                        'description' => 'Array of record identifiers.',
                        'items'       => [
                            'type'   => 'integer',
                            'format' => 'int32',
                        ],
                    ],
                ],
            ],
            'FilterRequest'       => [
                'id'         => 'FilterRequest',
                'properties' => [
                    'filter' => [
                        'type'        => 'string',
                        'description' => 'SQL or native filter to determine records where modifications will be applied.',
                    ],
                    'params' => [
                        'type'        => 'array',
                        'description' => 'Array of name-value pairs, used for parameter replacement on filters.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            'FilterRecordRequest' => [
                'id'         => 'FilterRecordRequest',
                'properties' => [
                    'record' => [
                        'type'        => 'RecordRequest',
                        'description' => 'A single record, array of fields, used to modify existing records.',
                    ],
                    'filter' => [
                        'type'        => 'string',
                        'description' => 'SQL or native filter to determine records where modifications will be applied.',
                    ],
                    'params' => [
                        'type'        => 'array',
                        'description' => 'Array of name-value pairs, used for parameter replacement on filters.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            'GetRecordsRequest'   => [
                'id'         => 'GetRecordsRequest',
                'properties' => [
                    'record' => [
                        'type'        => 'array',
                        'description' => 'Array of records.',
                        'items'       => [
                            '$ref' => 'RecordRequest',
                        ],
                    ],
                    'ids'    => [
                        'type'        => 'array',
                        'description' => 'Array of record identifiers.',
                        'items'       => [
                            'type'   => 'integer',
                            'format' => 'int32',
                        ],
                    ],
                    'filter' => [
                        'type'        => 'string',
                        'description' => 'SQL or native filter to determine records where modifications will be applied.',
                    ],
                    'params' => [
                        'type'        => 'array',
                        'description' => 'Array of name-value pairs, used for parameter replacement on filters.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            'RecordResponse'      => [
                'id'         => 'RecordResponse',
                'properties' => $_commonProperties
            ],
            'RecordsResponse'     => [
                'id'         => 'RecordsResponse',
                'properties' => [
                    'record' => [
                        'type'        => 'array',
                        'description' => 'Array of system user records.',
                        'items'       => [
                            '$ref' => 'RecordResponse',
                        ],
                    ],
                    'meta'   => [
                        'type'        => 'Metadata',
                        'description' => 'Array of metadata returned for GET requests.',
                    ],
                ],
            ],
            'Metadata'            => [
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
            ]
        ];

        $_base['apis'] = array_merge( $_base['apis'], $_apis );
        $_base['models'] = array_merge( $_base['models'], $_models );

        return $_base;
    }
}