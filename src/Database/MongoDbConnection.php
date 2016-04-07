<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Schema\Mongodb\Schema as MongoDbSchema;
use Jenssegers\Mongodb\Connection;

class MongoDbConnection extends Connection implements ConnectionInterface
{
    use ConnectionExtension;

    public static function checkRequirements()
    {
        if (!extension_loaded('mongodb')) {
            throw new \Exception("Required extension 'mongodb' is not detected, but may be compiled in.");
        }
    }

    public static function getDriverLabel()
    {
        return 'MongoDB';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-mysql.connection.php
        return 'mongodb://[username:password@]host1[:port1][,host2[:port2]][/[database][?options]]';
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new MongoDbSchema($this);
        }

        return $this->schemaExtension;
    }

    /**
     * @return boolean
     */
    public function supportsFunctions()
    {
        return false;
    }

    /**
     * @return boolean
     */
    public function supportsProcedures()
    {
        return false;
    }
}
