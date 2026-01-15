<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that can retrieve database extras
 */
interface DbExtrasInterface
{
    public function getServiceId();

    /**
     * @param string | array $table_names
     * @param string | array $select
     */
    public function getSchemaExtrasForTables($table_names, $select = '*');

    /**
     * @param string         $table_name
     * @param string | array $field_names
     * @param string | array $select
     */
    public function getSchemaExtrasForFields($table_name, $field_names = '*', $select = '*');

    /**
     * @param string         $table_name
     * @param string | array $related_names
     * @param string | array $select
     */
    public function getSchemaExtrasForRelated($table_name, $related_names = '*', $select = '*');

    /**
     * @param string         $table_name
     * @param string | array $select
     */
    public function getSchemaVirtualRelationships($table_name, $select = '*');

    /**
     * @param array $extras
     */
    public function setSchemaTableExtras($extras);

    /**
     * @param array $extras
     */
    public function setSchemaFieldExtras($extras);

    /**
     * @param array $extras
     */
    public function setSchemaRelatedExtras($extras);

    /**
     * @param array $relationships
     */
    public function setSchemaVirtualRelationships($relationships);

    /**
     * @param string | array $table_names
     */
    public function removeSchemaExtrasForTables($table_names);

    /**
     * @param string         $table_name
     * @param string | array $field_names
     */
    public function removeSchemaExtrasForFields($table_name, $field_names);

    /**
     * @param string         $table_name
     * @param string | array $related_names
     */
    public function removeSchemaExtrasForRelated($table_name, $related_names);

    /**
     * @param string         $table_name
     * @param string | array $relationships
     */
    public function removeSchemaVirtualRelationships($table_name, $relationships);

    /**
     * @param string $table_name
     */
    public function tablesDropped($table_name);

    /**
     * @param string $table_name
     * @param string $field_name
     */
    public function fieldsDropped($table_name, $field_name);
}
