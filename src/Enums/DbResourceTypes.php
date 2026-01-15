<?php
namespace DreamFactory\Core\Enums;


/**
 * DbResourceTypes
 * Database resource types
 */
class DbResourceTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const TYPE_FUNCTION = 'function';
    const TYPE_PROCEDURE = 'procedure';
    const TYPE_SCHEMA = 'schema';
    const TYPE_TABLE = 'table';
    const TYPE_TABLE_CONSTRAINT = 'table_constraint';
    const TYPE_TABLE_FIELD = 'table_field';
    const TYPE_TABLE_RELATIONSHIP = 'table_relationship';
    const TYPE_VIEW = 'view';
}
