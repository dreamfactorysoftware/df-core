<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
namespace DreamFactory\Rave\Utility;

use Config;
use DreamFactory\Rave\Models\DbServiceExtras;
use Log;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Rave\Exceptions\BadRequestException;

/**
 * DbUtilities
 * Generic database utilities
 */
class DbUtilities
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param $avail_fields
     *
     * @return array
     */
    public static function listAllFieldsFromDescribe( $avail_fields )
    {
        $out = array();
        foreach ( $avail_fields as $field_info )
        {
            $out[] = $field_info['name'];
        }

        return $out;
    }

    /**
     * @param $field_name
     * @param $avail_fields
     *
     * @return null
     */
    public static function getFieldFromDescribe( $field_name, $avail_fields )
    {
        foreach ( $avail_fields as $field_info )
        {
            if ( 0 == strcasecmp( $field_name, $field_info['name'] ) )
            {
                return $field_info;
            }
        }

        return null;
    }

    /**
     * @param $field_name
     * @param $avail_fields
     *
     * @return bool|int|string
     */
    public static function findFieldFromDescribe( $field_name, $avail_fields )
    {
        foreach ( $avail_fields as $key => $field_info )
        {
            if ( 0 == strcasecmp( $field_name, $field_info['name'] ) )
            {
                return $key;
            }
        }

        return false;
    }

    /**
     * @param $avail_fields
     *
     * @return string
     */
    public static function getPrimaryKeyFieldFromDescribe( $avail_fields )
    {
        foreach ( $avail_fields as $field_info )
        {
            if ( $field_info['is_primary_key'] )
            {
                return $field_info['name'];
            }
        }

        return '';
    }

    /**
     * @param array   $avail_fields
     * @param boolean $names_only Return only an array of names, otherwise return all properties
     *
     * @return array
     */
    public static function getPrimaryKeys( $avail_fields, $names_only = false )
    {
        $_keys = array();
        foreach ( $avail_fields as $_info )
        {
            if ( $_info['is_primary_key'] )
            {
                $_keys[] = ( $names_only ? $_info['name'] : $_info );
            }
        }

        return $_keys;
    }

    /**
     * @param int            $service_id
     * @param string | array $table_names
     * @param bool           $include_fields
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public static function getSchemaExtrasForTables( $service_id, $table_names, $include_fields = true, $select = '*' )
    {
        if ( empty( $service_id ) )
        {
            throw new \InvalidArgumentException( 'Invalid service id.' );
        }

        if ( false === $values = static::validateAsArray( $table_names, ',', true ) )
        {
            throw new \InvalidArgumentException( 'Invalid table list provided.' );
        }

        $call = DbServiceExtras::where('service_id', $service_id)->whereIn('table', $values);
        if ( !$include_fields )
        {
            $call->where('field', '');
        }

        return $call->get()->toArray();
    }

    /**
     * @param int            $service_id
     * @param string         $table_name
     * @param string | array $field_names
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public static function getSchemaExtrasForFields( $service_id, $table_name, $field_names, $select = '*' )
    {
        if ( empty( $service_id ) )
        {
            throw new \InvalidArgumentException( 'Invalid service id.' );
        }

        if ( false === $_values = static::validateAsArray( $field_names, ',', true ) )
        {
            throw new \InvalidArgumentException( 'Invalid field list. ' . $field_names );
        }

        $_results = DbServiceExtras::where('service_id', $service_id)->where('table', $table_name)->whereIn('field', $_values)->get()->toArray();

        return $_results;
    }

    /**
     * @param int   $service_id
     * @param array $labels
     *
     * @return void
     */
    public static function setSchemaExtras( $service_id, $labels )
    {
//        if ( empty( $labels ) )
//        {
//            return;
//        }
//
//        $_tables = array();
//        foreach ( $labels as $_label )
//        {
//            $_tables[] = ArrayUtils::get( $_label, 'table' );
//        }
//
//        $_tables = array_unique( $_tables );
//        $_oldRows = static::getSchemaExtrasForTables( $service_id, $_tables );
//
//        try
//        {
//            $_db = Pii::db();
//
//            $_inserts = $_updates = array();
//
//            foreach ( $labels as $_label )
//            {
//                $_table = ArrayUtils::get( $_label, 'table' );
//                $_field = ArrayUtils::get( $_label, 'field' );
//                $_id = null;
//                foreach ( $_oldRows as $_row )
//                {
//                    if ( ( ArrayUtils::get( $_row, 'table' ) == $_table ) && ( ArrayUtils::get( $_row, 'field' ) == $_field ) )
//                    {
//                        $_id = ArrayUtils::get( $_row, 'id' );
//                    }
//                }
//
//                if ( empty( $_id ) )
//                {
//                    $_inserts[] = $_label;
//                }
//                else
//                {
//                    $_updates[$_id] = $_label;
//                }
//            }
//
//            $_transaction = null;
//
//            try
//            {
//                $_transaction = $_db->beginTransaction();
//            }
//            catch ( \Exception $_ex )
//            {
//                //	No transaction support
//                $_transaction = false;
//            }
//
//            try
//            {
//                $_command = new \Command( $_db );
//
//                if ( !empty( $_inserts ) )
//                {
//                    foreach ( $_inserts as $_insert )
//                    {
//                        $_command->reset();
//                        $_insert['service_id'] = $service_id;
//                        $_command->insert( 'df_sys_schema_extras', $_insert );
//                    }
//                }
//
//                if ( !empty( $_updates ) )
//                {
//                    foreach ( $_updates as $_id => $_update )
//                    {
//                        $_command->reset();
//                        $_update['service_id'] = $service_id;
//                        $_command->update( 'df_sys_schema_extras', $_update, 'id = :id', array(':id' => $_id) );
//                    }
//                }
//
//                if ( $_transaction )
//                {
//                    $_transaction->commit();
//                }
//            }
//            catch ( \Exception $_ex )
//            {
//                Log::error( 'Exception storing schema updates: ' . $_ex->getMessage() );
//
//                if ( $_transaction )
//                {
//                    $_transaction->rollback();
//                }
//            }
//        }
//        catch ( \Exception $_ex )
//        {
//            Log::error( 'Failed to update df_sys_schema_extras. ' . $_ex->getMessage() );
//        }
    }

    /**
     * @param int            $service_id
     * @param string | array $table_names
     *
     */
    public static function removeSchemaExtrasForTables( $service_id, $table_names, $include_fields = true )
    {
//        try
//        {
//            $_db = Pii::db();
//            $_params = array();
//            $_where = array('and');
//
//            if ( empty( $service_id ) )
//            {
//                $_where[] = 'service_id IS NULL';
//            }
//            else
//            {
//                $_where[] = 'service_id = :id';
//                $_params[':id'] = $service_id;
//            }
//
//            if ( false === $_values = static::validateAsArray( $table_names, ',', true ) )
//            {
//                throw new \InvalidArgumentException( 'Invalid table list. ' . $table_names );
//            }
//
//            $_where[] = array('in', 'table', $_values);
//
//            if ( !$include_fields )
//            {
//                $_where[] = "field = ''";
//            }
//
//            $_db->createCommand()->delete( 'df_sys_schema_extras', $_where, $_params );
//        }
//        catch ( \Exception $_ex )
//        {
//            Log::error( 'Failed to delete from df_sys_schema_extras. ' . $_ex->getMessage() );
//        }
    }

    /**
     * @param int            $service_id
     * @param string         $table_name
     * @param string | array $field_names
     */
    public static function removeSchemaExtrasForFields( $service_id, $table_name, $field_names )
    {
//        try
//        {
//            $_db = Pii::db();
//            $_params = array();
//            $_where = array('and');
//
//            if ( empty( $service_id ) )
//            {
//                $_where[] = 'service_id IS NULL';
//            }
//            else
//            {
//                $_where[] = 'service_id = :id';
//                $_params[':id'] = $service_id;
//            }
//
//            $_where[] = 'table = :tn';
//            $_params[':tn'] = $table_name;
//
//            if ( false === $_values = static::validateAsArray( $field_names, ',', true ) )
//            {
//                throw new \InvalidArgumentException( 'Invalid field list. ' . $field_names );
//            }
//
//            $_where[] = array('in', 'field', $_values);
//
//            $_db->createCommand()->delete( 'df_sys_schema_extras', $_where, $_params );
//        }
//        catch ( \Exception $_ex )
//        {
//            Log::error( 'Failed to delete from df_sys_schema_extras. ' . $_ex->getMessage() );
//        }
    }

    /**
     * @param array $original
     *
     * @return array
     */
    public static function reformatFieldLabelArray( $original )
    {
        if ( empty( $original ) )
        {
            return array();
        }

        $_new = array();
        foreach ( $original as $_label )
        {
            $_new[ArrayUtils::get( $_label, 'field' )] = $_label;
        }

        return $_new;
    }

    /**
     * @param $type
     *
     * @return null|string
     */
    public static function determinePhpConversionType( $type )
    {
        switch ( $type )
        {
            case 'boolean':
                return 'bool';

            case 'integer':
            case 'id':
            case 'reference':
            case 'user_id':
            case 'user_id_on_create':
            case 'user_id_on_update':
                return 'int';

            case 'decimal':
            case 'float':
            case 'double':
                return 'float';

            case 'string':
            case 'text':
                return 'string';

            // special checks
            case 'date':
                return 'date';

            case 'time':
                return 'time';

            case 'datetime':
                return 'datetime';

            case 'timestamp':
            case 'timestamp_on_create':
            case 'timestamp_on_update':
                return 'timestamp';
        }

        return null;
    }

    /**
     * @param array | string $data          Array to check or comma-delimited string to convert
     * @param string | null  $str_delimiter Delimiter to check for string to array mapping, no op if null
     * @param boolean        $check_single  Check if single (associative) needs to be made multiple (numeric)
     * @param string | null  $on_fail       Error string to deliver in thrown exception
     *
     * @throws BadRequestException
     * @return array | boolean If requirements not met then throws exception if
     * $on_fail string given, or returns false. Otherwise returns valid array
     */
    public static function validateAsArray( $data, $str_delimiter = null, $check_single = false, $on_fail = null )
    {
        if ( !empty( $data ) && !is_array( $data ) && ( is_string( $str_delimiter ) && !empty( $str_delimiter ) ) )
        {
            $data = array_map( 'trim', explode( $str_delimiter, trim( $data, $str_delimiter ) ) );
        }

        if ( !is_array( $data ) || empty( $data ) )
        {
            if ( !is_string( $on_fail ) || empty( $on_fail ) )
            {
                return false;
            }

            throw new BadRequestException( $on_fail );
        }

        if ( $check_single )
        {
            if ( !isset( $data[0] ) )
            {
                // single record possibly passed in without wrapper array
                $data = array( $data );
            }
        }

        return $data;
    }

    public static function formatValue( $value, $type )
    {
        $type = strtolower( strval( $type ) );
        switch ( $type )
        {
            case 'int':
            case 'integer':
                return intval( $value );

            case 'decimal':
            case 'double':
            case 'float':
                return floatval( $value );

            case 'boolean':
            case 'bool':
                return Scalar::boolval( $value );

            case 'string':
                return strval( $value );

            case 'time':
            case 'date':
            case 'datetime':
            case 'timestamp':
                $_cfgFormat = static::getDateTimeFormat( $type );

                return static::formatDateTime( $_cfgFormat, $value );
        }

        return $value;
    }

    public static function getDateTimeFormat( $type )
    {
        switch ( strtolower( strval( $type ) ) )
        {
            case 'time':
                return Config::get( 'dsp.db_time_format' );

            case 'date':
                return Config::get( 'dsp.db_date_format' );

            case 'datetime':
                return Config::get( 'dsp.db_datetime_format' );

            case 'timestamp':
                return Config::get( 'dsp.db_timestamp_format' );
        }

        return null;
    }

    public static function formatDateTime( $out_format, $in_value = null, $in_format = null )
    {
        //  If value is null, current date and time are returned
        if ( !empty( $out_format ) )
        {
            $in_value = ( is_string( $in_value ) || is_null( $in_value ) ) ? $in_value : strval( $in_value );
            if ( !empty( $in_format ) )
            {
                if ( false === $_date = \DateTime::createfromFormat( $in_format, $in_value ) )
                {
                    Log::error( "Failed to format datetime from '$in_value'' to '$in_format'" );

                    return $in_value;
                }
            }
            else
            {
                $_date = new \DateTime( $in_value );
            }

            return $_date->format( $out_format );
        }

        return $in_value;
    }

    public static function findRecordByNameValue( $data, $field, $value )
    {
        foreach ( $data as $_record )
        {
            if ( ArrayUtils::get( $_record, $field ) === $value )
            {
                return $_record;
            }
        }

        return null;
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
                $_id = ArrayUtils::get( $record, $_field, null, $remove );
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
            $_id = ArrayUtils::get( $record, $_field, null, $remove );
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
