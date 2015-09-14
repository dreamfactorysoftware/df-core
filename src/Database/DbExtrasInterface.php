<?php
namespace DreamFactory\Core\Database;

/**
 * Something that can retrieve database extras
 */
interface DbExtrasInterface
{
    /**
     * @param string | array $table_names
     * @param bool           $include_fields
     * @param string | array $select
     */
    public function getSchemaExtrasForTables($table_names, $include_fields = true, $select = '*');

    /**
     * @param string         $table_name
     * @param string | array $field_names
     * @param string | array $select
     */
    public function getSchemaExtrasForFields($table_name, $field_names = '*', $select = '*');

    /**
     * @param array $extras
     */
    public function setSchemaTableExtras($extras);

    /**
     * @param array $extras
     */
    public function setSchemaFieldExtras($extras);

    /**
     * @param string | array $table_names
     */
    public function removeSchemaExtrasForTables($table_names);

    /**
     * @param string         $table_name
     * @param string | array $field_names
     */
    public function removeSchemaExtrasForFields($table_name, $field_names);
}
