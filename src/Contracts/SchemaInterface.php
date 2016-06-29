<?php
namespace DreamFactory\Core\Contracts;

interface SchemaInterface extends CacheInterface, DbExtrasInterface
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
     * @param       $field_info
     *
     * @return mixed
     */
    public function parseValueForSet($value, $field_info);

    /**
     * @param mixed  $value
     * @param string $type
     *
     * @return mixed
     */
    public function formatValue($value, $type);

    /**
     * @return string|null
     */
    public function getUserSchema();

    /**
     * @param string|null $schema
     */
    public function setUserSchema($schema);

    /**
     * @param $defaultSchemaOnly
     *
     * @return mixed
     */
    public function setDefaultSchemaOnly($defaultSchemaOnly);

    /**
     * @return mixed
     */
    public function isDefaultSchemaOnly();

    /**
     * @param      $tables
     * @param bool $allow_merge
     * @param bool $allow_delete
     * @param bool $rollback
     *
     * @return mixed
     */
    public function updateSchema($tables, $allow_merge = false, $allow_delete = false, $rollback = false);

    /**
     * @param      $table_name
     * @param      $fields
     * @param bool $allow_update
     * @param bool $allow_delete
     *
     * @return mixed
     */
    public function updateFields($table_name, $fields, $allow_update = false, $allow_delete = false);

    /**
     * @param      $name
     * @param bool $returnName
     *
     * @return mixed
     */
    public function doesTableExist($name, $returnName = false);

    /**
     * @param $table
     *
     * @return mixed
     */
    public function quoteTableName($table);

    /**
     * @param $column
     *
     * @return mixed
     */
    public function quoteColumnName($column);

    /**
     * @param $table
     *
     * @return mixed
     */
    public function dropTable($table);

    /**
     * @param $table
     * @param $column
     *
     * @return mixed
     */
    public function dropColumn($table, $column);

    /**
     * Set the Caching interface.
     *
     * @param  CacheInterface $cache
     */
    public function setCache($cache);

    /**
     * @return mixed
     */
    public function flushCache();

    /**
     * Set the DB Extras interface.
     *
     * @param  DbExtrasInterface $storage
     */
    public function setExtraStore($storage);

    /**
     * @return mixed
     */
    public function refresh();

    /**
     * Does this connection support stored functions
     *
     * @return boolean
     */
    public function supportsFunctions();

    /**
     * @param string $name
     * @param array  $in_params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callFunction($name, array $in_params);

    /**
     * Does this connection support stored procedures
     *
     * @return boolean
     */
    public function supportsProcedures();

    /**
     * @param string $name
     * @param array  $in_params
     * @param array  $out_params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callProcedure($name, array $in_params, array &$out_params);

    /**
     * @param mixed $field
     *
     * @return mixed
     */
    public function getPdoBinding($field);

    /**
     * @param mixed $field
     * @param boolean  $as_quoted_string
     *
     * @return string
     */
    public function parseFieldForSelect($field, $as_quoted_string = false);

    /**
     * @param mixed $field
     * @param boolean  $as_quoted_string
     *
     * @return string
     */
    public function parseFieldForFilter($field, $as_quoted_string = false);

    /**
     * @param string $type DbSimpleTypes value
     *
     * @return string Valid PHP type
     */
    public function determinePhpConversionType($type);
}