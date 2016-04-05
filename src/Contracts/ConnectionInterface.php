<?php
namespace DreamFactory\Core\Contracts;

interface ConnectionInterface extends \Illuminate\Database\ConnectionInterface, CacheInterface, DbExtrasInterface
{
    public static function checkRequirements();

    public static function getDriverLabel();

    public static function getSampleDsn();

    public static function adaptConfig(array &$config);

    /**
     * Return a Schema extension interface.
     *
     * @return SchemaInterface
     */
    public function getSchema();

    public function setDefaultSchemaOnly($defaultSchemaOnly);

    public function isDefaultSchemaOnly();

    /**
     * Set the Caching interface.
     *
     * @param  CacheInterface $cache
     */
    public function setCache($cache);

    public function flushCache();

    /**
     * Set the DB Extras interface.
     *
     * @param  DbExtrasInterface $storage
     */
    public function setExtraStore($storage);

    public function getUserName();

    public function selectColumn($query, $column = null, $bindings = [], $useReadPdo = true);

    public function selectValue($query, $column = null, $bindings = []);

    /**
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