<?php
namespace DreamFactory\Core\Contracts;

interface SchemaInterface
{
    /**
     * Return an array of table names.
     *
     * @param string $schema
     * @param bool   $include_views
     * @param bool   $refresh
     *
     * @return  []
     */
    public function getTableNames($schema = '', $include_views = true, $refresh = false);

    /**
     * @param string $name
     * @param bool   $refresh
     *
     * @return mixed
     */
    public function getTable($name, $refresh = false);

    /**
     * Return an array of table names.
     *
     * @param string $schema
     * @param bool   $refresh
     *
     * @return  []
     */
    public function getFunctionNames($schema = '', $refresh = false);

    /**
     * @param string $name
     * @param bool   $refresh
     *
     * @return mixed
     */
    public function getFunction($name, $refresh = false);

    /**
     * Return an array of table names.
     *
     * @param string $schema
     * @param bool   $refresh
     *
     * @return  []
     */
    public function getProcedureNames($schema = '', $refresh = false);

    /**
     * @param string $name
     * @param bool   $refresh
     *
     * @return mixed
     */
    public function getProcedure($name, $refresh = false);
    
    /**
     * @return string
     */
    public function getTimestampForSet();

    /**
     * @param mixed $value
     * @param $field_info
     *
     * @return mixed
     */
    public function parseValueForSet($value, $field_info);

    /**
     * @param mixed $value
     * @param string $type
     *
     * @return mixed
     */
    public function formatValue($value, $type);
    
    /**
     * @param array $schema
     *
     * @return mixed
     */
    public function updateSchema($schema);

    public function refresh();

}