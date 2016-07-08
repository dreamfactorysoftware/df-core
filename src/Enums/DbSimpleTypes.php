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

    const TYPE_BIGINT              = 'bigint';
    const TYPE_BINARY              = 'binary';
    const TYPE_BOOLEAN             = 'boolean';
    const TYPE_DATE                = 'date';
    const TYPE_DATETIME            = 'datetime';
    const TYPE_DECIMAL             = 'decimal';
    const TYPE_DOUBLE              = 'double';
    const TYPE_FLOAT               = 'float';
    const TYPE_ID                  = 'id';
    const TYPE_INTEGER             = 'integer';
    const TYPE_MONEY               = 'money';
    const TYPE_REF                 = 'reference';
    const TYPE_REF_CURSOR          = 'ref_cursor';
    const TYPE_STRING              = 'string';
    const TYPE_TEXT                = 'text';
    const TYPE_TIME                = 'time';
    const TYPE_TIMESTAMP           = 'timestamp';
    const TYPE_TIMESTAMP_ON_CREATE = 'timestamp_on_create';
    const TYPE_TIMESTAMP_ON_UPDATE = 'timestamp_on_update';
    const TYPE_USER_ID             = 'user_id';
    const TYPE_USER_ID_ON_CREATE   = 'user_id_on_create';
    const TYPE_USER_ID_ON_UPDATE   = 'user_id_on_update';
    const TYPE_VIRTUAL             = 'virtual';
}
