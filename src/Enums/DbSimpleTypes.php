<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * DbSimpleTypes
 * DB simplified data types for columns and stored procedure/function parameters
 */
class DbSimpleTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const TYPE_ID                  = 'id';
    const TYPE_REF                 = 'reference';
    const TYPE_BINARY              = 'binary';
    const TYPE_STRING              = 'string';
    const TYPE_TEXT                = 'text';
    const TYPE_BOOLEAN             = 'boolean';
    const TYPE_INTEGER             = 'integer';
    const TYPE_BIGINT              = 'bigint';
    const TYPE_DECIMAL             = 'decimal';
    const TYPE_DOUBLE              = 'double';
    const TYPE_FLOAT               = 'float';
    const TYPE_MONEY               = 'money';
    const TYPE_DATETIME            = 'datetime';
    const TYPE_DATE                = 'date';
    const TYPE_TIME                = 'time';
    const TYPE_TIMESTAMP           = 'timestamp';
    const TYPE_TIMESTAMP_ON_CREATE = 'timestamp_on_create';
    const TYPE_TIMESTAMP_ON_UPDATE = 'timestamp_on_update';
    const TYPE_USER_ID             = 'user_id';
    const TYPE_USER_ID_ON_CREATE   = 'user_id_on_create';
    const TYPE_USER_ID_ON_UPDATE   = 'user_id_on_update';
    const TYPE_VIRTUAL             = 'virtual';

    public static function getServerSideFilterOperators()
    {
        return [
            static::TYPE_ID,
            static::TYPE_REF,
            static::TYPE_BINARY,
            static::TYPE_STRING,
            static::TYPE_TEXT,
            static::TYPE_BOOLEAN,
            static::TYPE_INTEGER,
            static::TYPE_BIGINT,
            static::TYPE_DECIMAL,
            static::TYPE_DOUBLE,
            static::TYPE_FLOAT,
            static::TYPE_MONEY,
            static::TYPE_DATETIME,
            static::TYPE_DATE,
            static::TYPE_TIME,
            static::TYPE_TIMESTAMP,
            static::TYPE_TIMESTAMP_ON_CREATE,
            static::TYPE_TIMESTAMP_ON_UPDATE,
            static::TYPE_USER_ID,
            static::TYPE_USER_ID_ON_CREATE,
            static::TYPE_USER_ID_ON_UPDATE,
            static::TYPE_VIRTUAL,
        ];
    }
}
