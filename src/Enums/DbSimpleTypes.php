<?php

namespace DreamFactory\Core\Enums;


/**
 * DbSimpleTypes
 * DB simplified data types for columns and stored procedure/function parameters
 */
class DbSimpleTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const TYPE_ARRAY = 'array';
    const TYPE_BIG_ID = 'bigid';
    const TYPE_BIG_INT = 'bigint';
    const TYPE_BINARY = 'binary';
    const TYPE_BOOLEAN = 'boolean';
    const TYPE_COLUMN = 'column'; // mainly, routine return types
    const TYPE_DATE = 'date';
    const TYPE_DATETIME = 'datetime';
    const TYPE_DATETIME_TZ = 'datetimetz';
    const TYPE_DECIMAL = 'decimal';
    const TYPE_DOUBLE = 'double';
    const TYPE_FLOAT = 'float';
    const TYPE_ID = 'id';
    const TYPE_INTEGER = 'integer';
    const TYPE_JSON = 'json';
    const TYPE_JSONB = 'jsonb';
    const TYPE_LONG_TEXT = 'longtext';
    const TYPE_MEDIUM_ID = 'mediumid';
    const TYPE_MEDIUM_INTEGER = 'mediumint';
    const TYPE_MEDIUM_TEXT = 'mediumtext';
    const TYPE_MONEY = 'money';
    const TYPE_OBJECT = 'object';
    const TYPE_REF = 'reference';
    const TYPE_REF_CURSOR = 'ref_cursor'; // mainly, routine return types
    const TYPE_ROW = 'row'; // mainly, routine return types
    const TYPE_SMALL_ID = 'smallid';
    const TYPE_SMALL_INT = 'smallint';
    const TYPE_STRING = 'string';
    const TYPE_TABLE = 'table'; // mainly, routine return types
    const TYPE_TEXT = 'text';
    const TYPE_TIME = 'time';
    const TYPE_TIME_TZ = 'timetz';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_TIMESTAMP_TZ = 'timestamptz';
    const TYPE_TIMESTAMP_ON_CREATE = 'timestamp_on_create';
    const TYPE_TIMESTAMP_ON_UPDATE = 'timestamp_on_update';
    const TYPE_TINY_INT = 'tinyint';
    const TYPE_USER_ID = 'user_id';
    const TYPE_USER_ID_ON_CREATE = 'user_id_on_create';
    const TYPE_USER_ID_ON_UPDATE = 'user_id_on_update';
    const TYPE_UUID = 'uuid';

    // Cassandra specific Types
    const TYPE_TIME_UUID = 'timeuuid';

    public static function toPhpType($simple_type)
    {
        // supposedly map is faster than switch
        static $map = [
            DbSimpleTypes::TYPE_ARRAY               => 'array',
            DbSimpleTypes::TYPE_BIG_ID              => 'string', // due to php support issues
            // The "Big Integer" type is a 64bit data type. If for some crazy reason someone is still using a 32bit operating system, we cannot
            // convert this to an integer, as it would be too large for php to handle. So we need to check what the maximum integer size php can handle is.
            // If it is "8", that is 8 bytes, i.e 64 bits and so we can do a straight conversion from big int to int. if not, reassign as a string.
            DbSimpleTypes::TYPE_BIG_INT             => PHP_INT_SIZE === 8 ? "integer" : "string",
            DbSimpleTypes::TYPE_BINARY              => 'string',
            DbSimpleTypes::TYPE_BOOLEAN             => 'boolean',
            DbSimpleTypes::TYPE_DATE                => 'string',
            DbSimpleTypes::TYPE_DATETIME            => 'string',
            DbSimpleTypes::TYPE_DATETIME_TZ         => 'string',
            DbSimpleTypes::TYPE_DECIMAL             => 'double',
            DbSimpleTypes::TYPE_DOUBLE              => 'double',
            DbSimpleTypes::TYPE_FLOAT               => 'double',
            DbSimpleTypes::TYPE_ID                  => 'integer',
            DbSimpleTypes::TYPE_INTEGER             => 'integer',
            DbSimpleTypes::TYPE_JSON                => 'string',
            DbSimpleTypes::TYPE_JSONB               => 'string',
            DbSimpleTypes::TYPE_LONG_TEXT           => 'string',
            DbSimpleTypes::TYPE_MEDIUM_ID           => 'integer',
            DbSimpleTypes::TYPE_MEDIUM_INTEGER      => 'integer',
            DbSimpleTypes::TYPE_MEDIUM_TEXT         => 'string',
            DbSimpleTypes::TYPE_MONEY               => 'double',
            DbSimpleTypes::TYPE_OBJECT              => 'object',
            DbSimpleTypes::TYPE_REF                 => 'integer',
            DbSimpleTypes::TYPE_SMALL_ID            => 'integer',
            DbSimpleTypes::TYPE_SMALL_INT           => 'integer',
            DbSimpleTypes::TYPE_STRING              => 'string',
            DbSimpleTypes::TYPE_TEXT                => 'string',
            DbSimpleTypes::TYPE_TIME                => 'string',
            DbSimpleTypes::TYPE_TIME_TZ             => 'string',
            DbSimpleTypes::TYPE_TIMESTAMP           => 'string',
            DbSimpleTypes::TYPE_TIMESTAMP_TZ        => 'string',
            DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE => 'string',
            DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE => 'string',
            DbSimpleTypes::TYPE_TINY_INT            => 'integer',
            DbSimpleTypes::TYPE_USER_ID             => 'integer',
            DbSimpleTypes::TYPE_USER_ID_ON_CREATE   => 'integer',
            DbSimpleTypes::TYPE_USER_ID_ON_UPDATE   => 'integer',
            DbSimpleTypes::TYPE_UUID                => 'string',
//            DbSimpleTypes::TYPE_COLUMN            => 'object',
//            DbSimpleTypes::TYPE_ROW               => 'object',
//            DbSimpleTypes::TYPE_REF_CURSOR        => 'resource',
//            DbSimpleTypes::TYPE_TABLE             => 'object',
        ];

        $simple_type = strtolower(strval($simple_type));

        return isset($map[$simple_type]) ? $map[$simple_type] : null;
    }
}
