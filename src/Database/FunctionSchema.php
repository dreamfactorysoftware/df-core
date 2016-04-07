<?php
namespace DreamFactory\Core\Database;

/**
 * FunctionSchema is the base class for representing the metadata of a database function.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific table metadata.
 *
 * FunctionSchema provides the following information about a function:
 * <ul>
 * <li>{@link name}</li>
 * <li>{@link rawName}</li>
 * </ul>
 */
class FunctionSchema
{
    /**
     * @var string Name of the schema that this function belongs to.
     */
    public $schemaName;
    /**
     * @var string Name of this function.
     */
    public $funcName;
    /**
     * @var string Raw name of this function. This is the quoted version of function name with optional schema name.
     *      It can be directly used in SQL statements.
     */
    public $rawName;
    /**
     * @var string Public name of this function. This is the function name with optional non-default schema name.
     *      It is to be used by clients.
     */
    public $name;
}
