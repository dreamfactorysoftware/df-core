<?php
namespace DreamFactory\Core\Contracts;

use DreamFactory\Core\Database\Schema\TableSchema;

interface DbSchemaInterface
{
    /**
     * Return an array of supported schema resource types.
     *
     * @return array
     */
    public function getSupportedResourceTypes();

    /**
     * @param string $type Resource type
     *
     * @return boolean
     */
    public function supportsResourceType($type);

    /**
     * @param string $type Resource type
     * @param string $name
     * @param bool   $returnName
     *
     * @return mixed
     */
    public function doesResourceExist($type, $name, $returnName = false);

    /**
     * Return an array of names of a particular type of resource.
     *
     * @param string $type    Resource type
     * @param string $schema  Schema name if any specific requested
     *
     * @return array
     */
    public function getResourceNames($type, $schema = '');

    /**
     * Return the metadata about a particular schema resource.
     *
     * @param string $type    Resource type
     * @param string $name    Resource name
     *
     * @return mixed
     */
    public function getResource($type, &$name);

    /**
     * @param string $type Resource type
     * @param string $name Resource name
     *
     * @return mixed
     */
    public function dropResource($type, $name);

    /**
     * @return string|null
     */
    public function getDefaultSchema();

    /**
     * @param      $table
     * @param array $options
     *
     * @return mixed
     */
    public function createTable($table, $options);

    /**
     * @param TableSchema $tableSchema
     * @param array       $changes
     *
     * @throws \Exception
     */
    public function updateTable($tableSchema, $changes);

    /**
     * @param      $table
     *
     * @return mixed
     */
    public function dropTable($table);

    /**
     * @param string       $table
     * @param string|array $columns
     *
     * @return bool|int
     */
    public function dropColumns($table, $columns);

    /**
     * @param array $indexes
     *
     */
    public function createFieldIndexes($indexes);

    /**
     * @param array $references
     *
     */
    public function createFieldReferences($references);

    /**
     * @param string $table
     * @param        $relationship
     *
     * @return bool|int
     */
    public function dropRelationship($table, $relationship);

    /**
     * @param $type
     * @return mixed
     */
    public static function isUndiscoverableType($type);

    /**
     * @param bool $unique
     * @param bool $on_create_table
     *
     * @return bool
     */
    public function requiresCreateIndex($unique = false, $on_create_table = false);

    /**
     * @return bool
     */
    public function allowsSeparateForeignConstraint();

    /**
     * @param $table
     * @param $column
     *
     * @return array
     */
    public function getPrimaryKeyCommands($table, $column);

    /**
     * @param string      $prefix
     * @param string      $table
     * @param string|null $column
     *
     * @return string
     */
    public function makeConstraintName($prefix, $table, $column = null);

    /**
     * @param string $name
     * @param array  $in_params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callFunction($name, array $in_params);

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
     * @param mixed $value
     * @param mixed $field_info
     * @param boolean $allow_null
     *
     * @return mixed
     */
    public function typecastToNative($value, $field_info, $allow_null = true);

    /**
     * @param mixed   $value
     * @param mixed   $field_info
     * @param boolean $allow_null
     *
     * @return mixed
     */
    public function typecastToClient($value, $field_info, $allow_null = true);

    /**
     * @return string
     */
    public function getTimestampForSet();

    /**
     * @param string $name
     * @return string
     */
    public function quoteTableName($name);

    /**
     * @param string $name
     * @return string
     */
    public function quoteColumnName($name);
}