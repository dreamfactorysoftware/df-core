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
use DreamFactory\Rave\Services\Swagger;
use DreamFactory\Rave\Utility\DbUtilities;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\RestException;
use DreamFactory\Rave\Services\BaseDbService;

abstract class BaseDbSchemaResource extends BaseDbResource
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

    //*************************************************************************
    //	Methods
    //*************************************************************************

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
        $this->correctTableName( $table );
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
                $result = [ 'table' => $this->describeTables( $tables, $refresh ) ];
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

            $result = [ 'table' => $this->createTables( $tables, $checkExist, $returnSchema ) ];
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

            $result = [ 'table' => $this->updateTables( $tables, true, $returnSchema ) ];
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

            $result = [ 'table' => $this->updateTables( $tables, false, $returnSchema ) ];
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

            $result = [ 'table' => $result ];
        }
        elseif ( empty( $this->resourceId ) )
        {
            $this->deleteTable( $this->resource );

            $result = [ 'success' => true ];
        }
        else
        {
            $this->deleteField( $this->resource, $this->resourceId );

            $result = [ 'success' => true ];
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

        $out = [];
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

        $out = [];
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
    abstract public function createTable( $table, $properties = [], $check_exist = false, $return_schema = false );

    /**
     * Create a single table field by name and additional properties
     *
     * @param string $table
     * @param string $field
     * @param array  $properties
     * @param bool   $check_exist
     * @param bool   $return_schema Return a refreshed copy of the schema from the database
     */
    abstract public function createField( $table, $field, $properties = [], $check_exist = false, $return_schema = false );

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
        $out = [];
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

        $out = [];
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

    public function getApiDocInfo()
    {
        $_base = parent::getApiDocInfo();

        $_commonResponses = Swagger::getCommonResponses();

        $_apis = [
            [
                'path'        => '/{api_name}/' . static::RESOURCE_NAME,
                'description' => 'Operations available for SQL DB Schemas.',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getSchemas() - List resources available for database schema.',
                        'nickname'         => 'getSchemas',
                        'type'             => 'Resources',
                        'event_name'       => '{api_name}._schema.list',
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
                    [
                        'method'           => 'POST',
                        'summary'          => 'createTables() - Create one or more tables.',
                        'nickname'         => 'createTables',
                        'type'             => 'Resources',
                        'event_name'       => '{api_name}._schema.create',
                        'parameters'       => [
                            [
                                'name'          => 'tables',
                                'description'   => 'Array of table definitions.',
                                'allowMultiple' => false,
                                'type'          => 'TableSchemas',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be a single table definition or an array of table definitions.',
                    ],
                    [
                        'method'           => 'PUT',
                        'summary'          => 'replaceTables() - Update (replace) one or more tables.',
                        'nickname'         => 'replaceTables',
                        'event_name'       => '{api_name}._schema.alter',
                        'type'             => 'Resources',
                        'parameters'       => [
                            [
                                'name'          => 'tables',
                                'description'   => 'Array of table definitions.',
                                'allowMultiple' => false,
                                'type'          => 'TableSchemas',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be a single table definition or an array of table definitions.',
                    ],
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'updateTables() - Update (patch) one or more tables.',
                        'nickname'         => 'updateTables',
                        'event_name'       => '{api_name}._schema.alter',
                        'type'             => 'Resources',
                        'parameters'       => [
                            [
                                'name'          => 'tables',
                                'description'   => 'Array of table definitions.',
                                'allowMultiple' => false,
                                'type'          => 'TableSchemas',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be a single table definition or an array of table definitions.',
                    ],
                ],
            ],
            [
                'path'        => '/{api_name}/' . static::RESOURCE_NAME . '/{table_name}',
                'description' => 'Operations for per table administration.',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'describeTable() - Retrieve table definition for the given table.',
                        'nickname'         => 'describeTable',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.describe',
                            '{api_name}._schema.table_described'
                        ],
                        'type'             => 'TableSchema',
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
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the schema.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'This describes the table, its fields and relations to other tables.',
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'createTable() - Create a table with the given properties and fields.',
                        'nickname'         => 'createTable',
                        'type'             => 'Success',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.create',
                            '{api_name}._schema.table_created'
                        ],
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
                                'name'          => 'schema',
                                'description'   => 'Array of table properties and fields definitions.',
                                'allowMultiple' => false,
                                'type'          => 'TableSchema',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
                    ],
                    [
                        'method'           => 'PUT',
                        'summary'          => 'replaceTable() - Update (replace) a table with the given properties.',
                        'nickname'         => 'replaceTable',
                        'type'             => 'Success',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.alter',
                            '{api_name}._schema.table_altered'
                        ],
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
                                'name'          => 'schema',
                                'description'   => 'Array of field definitions.',
                                'allowMultiple' => false,
                                'type'          => 'TableSchema',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
                    ],
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'updateTable() - Update (patch) a table with the given properties.',
                        'nickname'         => 'updateTable',
                        'type'             => 'Success',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.alter',
                            '{api_name}._schema.table_altered'
                        ],
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
                                'name'          => 'schema',
                                'description'   => 'Array of field definitions.',
                                'allowMultiple' => false,
                                'type'          => 'TableSchema',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteTable() - Delete (aka drop) the given table.',
                        'nickname'         => 'deleteTable',
                        'type'             => 'Success',
                        'event_name'       => [ '{api_name}._schema.{table_name}.drop', '{api_name}._schema.table_dropped' ],
                        'parameters'       => [
                            [
                                'name'          => 'table_name',
                                'description'   => 'Name of the table to perform operations on.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Careful, this drops the database table and all of its contents.',
                    ],
                ],
            ],
            [
                'path'        => '/{api_name}/' . static::RESOURCE_NAME . '/{table_name}/{field_name}',
                'description' => 'Operations for single field administration.',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'describeField() - Retrieve the definition of the given field for the given table.',
                        'nickname'         => 'describeField',
                        'type'             => 'FieldSchema',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.{field_name}.describe',
                            '{api_name}._schema.{table_name}.field_described'
                        ],
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
                                'name'          => 'field_name',
                                'description'   => 'Name of the field to perform operations on.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the schema.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'This describes the field and its properties.',
                    ],
                    [
                        'method'           => 'PUT',
                        'summary'          => 'replaceField() - Update one record by identifier.',
                        'nickname'         => 'replaceField',
                        'type'             => 'Success',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.{field_name}.alter',
                            '{api_name}._schema.{table_name}.field_altered'
                        ],
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
                                'name'          => 'field_name',
                                'description'   => 'Name of the field to perform operations on.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'properties',
                                'description'   => 'Array of field properties.',
                                'allowMultiple' => false,
                                'type'          => 'FieldSchema',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be an array of field properties for the given field.',
                    ],
                    [
                        'method'           => 'PATCH',
                        'summary'          => 'updateField() - Update one record by identifier.',
                        'nickname'         => 'updateField',
                        'type'             => 'Success',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.{field_name}.alter',
                            '{api_name}._schema.{table_name}.field_altered'
                        ],
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
                                'name'          => 'field_name',
                                'description'   => 'Name of the field to perform operations on.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                            [
                                'name'          => 'properties',
                                'description'   => 'Array of field properties.',
                                'allowMultiple' => false,
                                'type'          => 'FieldSchema',
                                'paramType'     => 'body',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Post data should be an array of field properties for the given field.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteField() - Remove the given field from the given table.',
                        'nickname'         => 'deleteField',
                        'type'             => 'Success',
                        'event_name'       => [
                            '{api_name}._schema.{table_name}.{field_name}.drop',
                            '{api_name}._schema.{table_name}.field_dropped'
                        ],
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
                                'name'          => 'field_name',
                                'description'   => 'Name of the field to perform operations on.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => $_commonResponses,
                        'notes'            => 'Careful, this drops the database table field/column and all of its contents.',
                    ],
                ],
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
        ];

        $_base['apis'] = array_merge( $_base['apis'], $_apis );
        $_base['models'] = array_merge( $_base['models'], $_models );

        return $_base;
    }
}