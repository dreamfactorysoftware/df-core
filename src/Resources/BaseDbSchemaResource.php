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

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Utility\DbUtilities;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\RestException;
use DreamFactory\Rave\Services\BaseDbService;

// Handle administrative options, table add, delete, etc
abstract class BaseDbSchemaResource extends BaseRestResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Resource tag for dealing with table schema
     */
    const RESOURCE_NAME = '_schema';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var null|BaseDbService
     */
    protected $service = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param BaseDbService $service
     * @param array $settings
     */
    public function __construct( $service = null, $settings = array() )
    {
        parent::__construct( $settings );

        $this->service = $service;
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
    protected function validateSchemaAccess( $table = null, $action = null )
    {
        $resource = static::RESOURCE_NAME;
        $this->correctTableName($table);
        $this->service->validateResourceAccess( $resource, $table, $action );
    }

    /**
     * @return array|bool
     * @throws BadRequestException
     */
    protected function handleGET()
    {
        $options = $this->request->query();
        $payload = $this->request->getPayloadData();

        $refresh = ArrayUtils::getBool( $options, 'refresh' );
        if ( empty( $this->resource ) )
        {
            $tables = ArrayUtils::get( $options, 'names' );
            if ( empty( $tables ) )
            {
                $tables = ArrayUtils::get( $payload, 'table' );
            }

            if ( !empty( $tables ) )
            {
                $result = array( 'table' => $this->describeTables( $tables, $refresh ) );
            }
            else
            {
                $result = parent::handleGET();
            }
        }
        elseif ( empty( $this->resourceId ) )
        {
            $result = $this->describeTable( $this->resource, $refresh );
        }
        else
        {
            $result = $this->describeField( $this->resource, $this->resourceId, $refresh );
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws BadRequestException
     */
    protected function handlePOST()
    {
        $options = $this->request->query();
        $payload = $this->request->getPayloadData();

        $checkExist = ArrayUtils::getBool( $options, 'check_exist' );
        $returnSchema = ArrayUtils::getBool( $options, 'return_schema' );
        if ( empty( $this->resource ) )
        {
            $tables = ArrayUtils::get( $payload, 'table', $payload );
            if ( empty( $tables ) )
            {
                throw new BadRequestException( 'No data in schema create request.' );
            }

            $result = array( 'table' => $this->createTables( $this->resource, $checkExist, $returnSchema ) );
        }
        elseif ( empty( $this->resourceId ) )
        {
            $result = $this->createTable( $this->resource, $payload, $checkExist, $returnSchema );
        }
        elseif ( empty( $payload ) )
        {
            throw new BadRequestException( 'No data in schema create request.' );
        }
        else
        {
            $result = $this->createField( $this->resource, $this->resourceId, $payload, $checkExist, $returnSchema );
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws BadRequestException
     */
    protected function handlePUT()
    {
        $options = $this->request->query();
        $payload = $this->request->getPayloadData();

        $returnSchema = ArrayUtils::getBool( $options, 'return_schema' );
        if ( empty( $this->resource ) )
        {
            $tables = ArrayUtils::get( $payload, 'table', $payload );
            if ( empty( $tables ) )
            {
                throw new BadRequestException( 'No data in schema update request.' );
            }

            $result = array( 'table' => $this->updateTables( $tables, true, $returnSchema ) );
        }
        elseif ( empty( $this->resourceId ) )
        {
            $result = $this->updateTable( $this->resource, $payload, true, $returnSchema );
        }
        elseif ( empty( $payload ) )
        {
            throw new BadRequestException( 'No data in schema update request.' );
        }
        else
        {
            $result = $this->updateField( $this->resource, $this->resourceId, $payload, true, $returnSchema );
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws BadRequestException
     */
    protected function handlePATCH()
    {
        $options = $this->request->query();
        $payload = $this->request->getPayloadData();

        $returnSchema = ArrayUtils::getBool( $options, 'return_schema' );
        if ( empty( $this->resource ) )
        {
            $tables = ArrayUtils::get( $payload, 'table', $payload );
            if ( empty( $tables ) )
            {
                throw new BadRequestException( 'No data in schema update request.' );
            }

            $result = array( 'table' => $this->updateTables( $tables, false, $returnSchema ) );
        }
        elseif ( empty( $this->resourceId ) )
        {
            $result = $this->updateTable( $this->resource, $options, false, $returnSchema );
        }
        elseif ( empty( $payload ) )
        {
            throw new BadRequestException( 'No data in schema update request.' );
        }
        else
        {
            $result = $this->updateField( $this->resource, $this->resourceId, $payload, false, $returnSchema );
        }

        return $result;
    }

    /**
     * @return array|bool
     * @throws BadRequestException
     */
    protected function handleDELETE()
    {
        $options = $this->request->query();
        $payload = $this->request->getPayloadData();

        if ( empty( $this->resource ) )
        {
            $tables = ArrayUtils::get( $options, 'names' );
            if ( empty( $tables ) )
            {
                $tables = ArrayUtils::get( $payload, 'table' );
            }

            if ( empty( $tables ) )
            {
                throw new BadRequestException( 'No data in schema delete request.' );
            }

            $result = $this->deleteTables( $tables );

            $result = array( 'table' => $result );
        }
        elseif ( empty( $this->resourceId ) )
        {
            $this->deleteTable( $this->resource );

            $result = array( 'success' => true );
        }
        else
        {
            $this->deleteField( $this->resource, $this->resourceId );

            $result = array( 'success' => true );
        }

        return $result;
    }

    /**
     * Check if the table exists in the database
     *
     * @param string $table_name Table name
     *
     * @return boolean
     * @throws \Exception
     */
    public function doesTableExist( $table_name )
    {
        try
        {
            $this->correctTableName( $table_name );

            return true;
        }
        catch ( \Exception $ex )
        {

        }

        return false;
    }

    /**
     * Get multiple tables and their properties
     *
     * @param string | array $tables  Table names comma-delimited string or array
     * @param bool           $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function describeTables( $tables, $refresh = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $out = array();
        foreach ( $tables as $table )
        {
            $name = ( is_array( $table ) ) ? ArrayUtils::get( $table, 'name' ) : $table;
            $this->validateSchemaAccess( $name, Verbs::GET );

            $out[] = $this->describeTable( $table, $refresh );
        }

        return $out;
    }

    /**
     * Get any properties related to the table
     *
     * @param string | array $table   Table name or defining properties
     * @param bool           $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function describeTable( $table, $refresh = false );

    /**
     * Get any properties related to the table field
     *
     * @param string $table   Table name
     * @param string $field   Table field name
     * @param bool   $refresh Force a refresh of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function describeField( $table, $field, $refresh = false );

    /**
     * Create one or more tables by array of table properties
     *
     * @param string|array $tables
     * @param bool         $check_exist
     * @param bool         $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    public function createTables( $tables, $check_exist = false, $return_schema = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $out = array();
        foreach ( $tables as $table )
        {
            $name = ( is_array( $table ) ) ? ArrayUtils::get( $table, 'name' ) : $table;
            $out[] = $this->createTable( $name, $table, $check_exist, $return_schema );
        }

        return $out;
    }

    /**
     * Create a single table by name and additional properties
     *
     * @param string $table
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     */
    abstract public function createTable( $table, $properties = array(), $check_exist = false, $return_schema = false );

    /**
     * Create a single table field by name and additional properties
     *
     * @param string $table
     * @param string $field
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     */
    abstract public function createField( $table, $field, $properties = array(), $check_exist = false, $return_schema = false );

    /**
     * Update one or more tables by array of table properties
     *
     * @param array $tables
     * @param bool  $allow_delete_fields
     * @param bool  $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     */
    public function updateTables( $tables, $allow_delete_fields = false, $return_schema = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            null,
            true,
            'The request contains no valid table properties.'
        );

        // update tables allows for create as well
        $out = array();
        foreach ( $tables as $table )
        {
            $name = ( is_array( $table ) ) ? ArrayUtils::get( $table, 'name' ) : $table;
            if ( $this->doesTableExist( $name ) )
            {
                $this->validateSchemaAccess( $name, Verbs::PATCH );
                $out[] = $this->updateTable( $name, $table, $allow_delete_fields, $return_schema );
            }
            else
            {
                $this->validateSchemaAccess( null, Verbs::POST );
                $out[] = $this->createTable( $name, $table, false, $return_schema );
            }
        }

        return $out;
    }

    /**
     * Update properties related to the table
     *
     * @param string $table
     * @param array  $properties
     * @param bool   $allow_delete_fields
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function updateTable( $table, $properties, $allow_delete_fields = false, $return_schema = false );

    /**
     * Update properties related to the table
     *
     * @param string $table
     * @param string $field
     * @param array  $properties
     * @param bool   $allow_delete_parts
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     *
     * @return array
     * @throws \Exception
     */
    abstract public function updateField( $table, $field, $properties, $allow_delete_parts = false, $return_schema = false );

    /**
     * Delete multiple tables and all of their contents
     *
     * @param array $tables
     * @param bool  $check_empty
     *
     * @return array
     * @throws \Exception
     */
    public function deleteTables( $tables, $check_empty = false )
    {
        $tables = DbUtilities::validateAsArray(
            $tables,
            ',',
            true,
            'The request contains no valid table names or properties.'
        );

        $out = array();
        foreach ( $tables as $table )
        {
            $name = ( is_array( $table ) ) ? ArrayUtils::get( $table, 'name' ) : $table;
            $this->validateSchemaAccess( $name, Verbs::DELETE );
            $out[] = $this->deleteTable( $table, $check_empty );
        }

        return $out;
    }

    /**
     * Delete a table and all of its contents by name
     *
     * @param string $table
     * @param bool   $check_empty
     *
     * @throws \Exception
     * @return array
     */
    abstract public function deleteTable( $table, $check_empty = false );

    /**
     * Delete a table field
     *
     * @param string $table
     * @param string $field
     *
     * @throws \Exception
     * @return array
     */
    abstract public function deleteField( $table, $field );

}