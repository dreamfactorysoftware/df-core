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
use DreamFactory\Rave\Contracts\ServiceRequestInterface;
use DreamFactory\Rave\Services\BaseDbService;
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
    protected $_batchIds = array();
    /**
     * @var array
     */
    protected $_batchRecords = array();
    /**
     * @var array
     */
    protected $_rollbackRecords = array();

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
                        $this->payload = array( static::RECORD_WRAPPER => array( $this->payload ) );
                    }
                }
            }
            elseif ( ArrayUtils::isArrayNumeric( $this->payload ) )
            {
                // import from csv, etc doesn't include a wrapper, so wrap it
                $this->payload = array( static::RECORD_WRAPPER => $this->payload );
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
                                $this->payload[static::RECORD_WRAPPER] = array( $this->payload );
                            }
                            break;
                    }
                }
            }

            $this->options = $this->getQueryData();

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
        if ( empty( $this->resource))
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
                $_params = ArrayUtils::get( $this->options, 'params', array() );

                $_result = $this->retrieveRecordsByFilter( $this->resource, $_filter, $_params, $this->options );
            }
        }

        $_meta = ArrayUtils::get( $_result, 'meta' );
        unset( $_result['meta'] );
        $_result = array( static::RECORD_WRAPPER => $_result );

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
        if ( empty( $this->resource))
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
        $_result = array( static::RECORD_WRAPPER => $_result );
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
        if ( empty( $this->resource))
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
                $_params = ArrayUtils::get( $this->options, 'params', array() );
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
        $_result = array( static::RECORD_WRAPPER => $_result );
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
        if ( empty( $this->resource))
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
                $_params = ArrayUtils::get( $this->options, 'params', array() );
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
        $_result = array( static::RECORD_WRAPPER => $_result );
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
        if ( empty( $this->resource))
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
                    $_params = ArrayUtils::get( $this->options, 'params', array() );
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
        $_result = array( static::RECORD_WRAPPER => $_result );
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
    public function createRecords( $table, $records, $extras = array() )
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

        $_out = array();
        $_errors = array();
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
                $_context = array( 'error' => $_errors, static::RECORD_WRAPPER => $_out );
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
                $_ex->setContext($_context);
                $_ex->setMessage($_msg);
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
    public function createRecord( $table, $record, $extras = array() )
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
    public function updateRecords( $table, $records, $extras = array() )
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

        $_out = array();
        $_errors = array();
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
                $_context = array( 'error' => $_errors, static::RECORD_WRAPPER => $_out );
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
                $_ex->setContext($_context);
                $_ex->setMessage($_msg);
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
    public function updateRecord( $table, $record, $extras = array() )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->updateRecords( $table, $_records, $extras );

        return ArrayUtils::get( $_results, 0, array() );
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
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
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
    public function updateRecordsByIds( $table, $record, $ids, $extras = array() )
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

        $_out = array();
        $_errors = array();
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
                $_context = array( 'error' => $_errors, static::RECORD_WRAPPER => $_out );
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
                $_ex->setContext($_context);
                $_ex->setMessage($_msg);
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
    public function updateRecordById( $table, $record, $id, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->updateRecordsByIds( $table, $record, $id, $extras );

        return ArrayUtils::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function patchRecords( $table, $records, $extras = array() )
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

        $_out = array();
        $_errors = array();
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
                $_context = array( 'error' => $_errors, static::RECORD_WRAPPER => $_out );
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
                $_ex->setContext($_context);
                $_ex->setMessage($_msg);
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
    public function patchRecord( $table, $record, $extras = array() )
    {
        $_records = DbUtilities::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->patchRecords( $table, $_records, $extras );

        return ArrayUtils::get( $_results, 0, array() );
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
    public function patchRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
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
    public function patchRecordsByIds( $table, $record, $ids, $extras = array() )
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

        $_out = array();
        $_errors = array();
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
                $_context = array( 'error' => $_errors, static::RECORD_WRAPPER => $_out );
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
                $_ex->setContext($_context);
                $_ex->setMessage($_msg);
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
    public function patchRecordById( $table, $record, $id, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->patchRecordsByIds( $table, $record, $id, $extras );

        return ArrayUtils::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecords( $table, $records, $extras = array() )
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

        $_ids = array();
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
    public function deleteRecord( $table, $record, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->deleteRecords( $table, array( $record ), $extras );

        return ArrayUtils::get( $_results, 0, array() );
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
    public function deleteRecordsByFilter( $table, $filter, $params = array(), $extras = array() )
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
    public function deleteRecordsByIds( $table, $ids, $extras = array() )
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

        $_out = array();
        $_errors = array();
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
                $_context = array( 'error' => $_errors, static::RECORD_WRAPPER => $_out );
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
                $_ex->setContext($_context);
                $_ex->setMessage($_msg);
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
    public function deleteRecordById( $table, $id, $extras = array() )
    {
        $_results = $this->deleteRecordsByIds( $table, $id, $extras );

        return ArrayUtils::get( $_results, 0, array() );
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
    public function truncateTable( $table, $extras = array() )
    {
        // todo faster way?
        $_records = $this->retrieveRecordsByFilter( $table, null, null, $extras );

        if ( !empty( $_records ) )
        {
            $this->deleteRecords( $table, $_records, $extras );
        }

        return array( 'success' => true );
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
    abstract public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() );

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecords( $table, $records, $extras = array() )
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

        $_ids = array();
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
    public function retrieveRecord( $table, $record, $extras = array() )
    {
        $record = DbUtilities::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->retrieveRecords( $table, array( $record ), $extras );

        return ArrayUtils::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
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

        $_out = array();
        $_errors = array();
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
                $_context = array( 'error' => $_errors, static::RECORD_WRAPPER => $_out );
                $_msg = 'Batch Error: Not all records could be retrieved.';
            }

            if ( $_ex instanceof RestException )
            {
                $_temp = $_ex->getContext();
                $_context = ( empty( $_temp ) ) ? $_context : $_temp;
                $_ex->setContext($_context);
                $_ex->setMessage($_msg);
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
    public function retrieveRecordById( $table, $id, $extras = array() )
    {
        $_results = $this->retrieveRecordsByIds( $table, $id, $extras );

        return ArrayUtils::get( $_results, 0, array() );
    }

    /**
     * @param mixed $handle
     *
     * @return bool
     */
    protected function initTransaction( $handle = null )
    {
        $this->_transactionTable = $handle;
        $this->_batchRecords = array();
        $this->_batchIds = array();
        $this->_rollbackRecords = array();

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

        return array();
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
        $_fields = array();
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
                $_id = array();
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
            return array();
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
        $_parsed = ( empty( $fields_info ) ) ? $record : array();
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

                        $_options = array();
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
                        $_options = array( 'options' => $_options, 'flags' => $_flags );
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
                        $_options = array( 'options' => $_options );
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
                        $_options = array( 'regexp' => $_regex );
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
        return intval( Config::get( 'dsp.db_max_records_returned', static::MAX_RECORDS_RETURNED ) );
    }

    /**
     * @param array        $record
     * @param string|array $include  List of keys to include in the output record
     * @param string|array $id_field Single or list of identifier fields
     *
     * @return array
     */
    protected static function cleanRecord( $record = array(), $include = '*', $id_field = null )
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
            $_out = array();
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
        $_out = array();
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
        $_out = array();
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
            return array();
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        if ( count( $id_field ) > 1 )
        {
            $_ids = array();
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

            return ( $include_field ) ? array( $_field => $_id ) : $_id;
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
            return array();
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        $_out = array();
        foreach ( $ids as $_id )
        {
            $_ids = array();
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
            return array();
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

}