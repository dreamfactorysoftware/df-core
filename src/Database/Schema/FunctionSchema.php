<?php
namespace DreamFactory\Core\Database\Schema;

/**
 * FunctionSchema is the base class for representing the metadata of a database function.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific table metadata.
 *
 * FunctionSchema provides the following information about a function:
 */
class FunctionSchema extends RoutineSchema
{
}
