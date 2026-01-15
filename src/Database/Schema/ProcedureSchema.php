<?php
namespace DreamFactory\Core\Database\Schema;

/**
 * ProcedureSchema is the base class for representing the metadata of a database procedure.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific procedure metadata.
 *
 * ProcedureSchema provides the following information about a procedure:
 */
class ProcedureSchema extends RoutineSchema
{
}
