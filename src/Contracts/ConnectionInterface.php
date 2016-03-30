<?php
namespace DreamFactory\Core\Contracts;

use DreamFactory\Core\Database\DbExtrasInterface;

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

}