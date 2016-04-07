<?php
namespace DreamFactory\Core\Database;

/**
 * ProcedureSchema is the base class for representing the metadata of a database procedure.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific procedure metadata.
 *
 * ProcedureSchema provides the following information about a procedure:
 * <ul>
 * <li>{@link name}</li>
 * <li>{@link rawName}</li>
 * </ul>
 */
class ProcedureSchema
{
    /**
     * @var string Name of the schema that this procedure belongs to.
     */
    public $schemaName;
    /**
     * @var string Name of this procedure.
     */
    public $procName;
    /**
     * @var string Raw name of this procedure. This is the quoted version of procedure name with optional schema name.
     *      It can be directly used in SQLs.
     */
    public $rawName;
    /**
     * @var string Public name of this procedure. This is the procedure name with optional non-default schema name.
     *      It is to be used by clients.
     */
    public $name;

}
