<?php
namespace DreamFactory\Core\Contracts;

interface ConnectionInterface extends \Illuminate\Database\ConnectionInterface
{
    /**
     * Check for any requirements to support this connection
     *
     * @throws \Exception
     */
    public static function checkRequirements();

    /**
     * Get the driver label for this connection
     *
     * @return string
     */
    public static function getDriverLabel();

    /**
     * Get a sample DSN (connection string) layout for this connection
     *
     * @return string
     */
    public static function getSampleDsn();

    /**
     * Adapt the config to any requirements of this connection
     * 
     * @param array $config
     */
    public static function adaptConfig(array &$config);

    /**
     * Return a schema extension interface.
     *
     * @return SchemaInterface
     */
    public function getSchema();

    /**
     * Return any username associated with this connection.
     *
     * @return string|null
     */
    public function getUserName();

    /**
     * Return any table prefix associated with this connection.
     *
     * @return string|null
     */
    public function getTablePrefix();

    /**
     * Select but only return the requested or first column.
     *
     * @param string      $query
     * @param string|null $column
     * @param array       $bindings
     * @param bool        $useReadPdo
     *
     * @return array
     */
    public function selectColumn($query, $column = null, $bindings = [], $useReadPdo = true);

    /**
     * Select but only return the first value in the requested or first column.
     *
     * @param string      $query
     * @param string|null $column
     * @param array       $bindings
     *
     * @return mixed
     */
    public function selectValue($query, $column = null, $bindings = []);

    /**
     * Does this connection support stored functions
     *
     * @return boolean
     */
    public function supportsFunctions();

    /**
     * @param string $name
     * @param array  $params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callFunction($name, &$params);

    /**
     * Does this connection support stored procedures
     *
     * @return boolean
     */
    public function supportsProcedures();

    /**
     * @param string $name
     * @param array  $params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callProcedure($name, &$params);
}