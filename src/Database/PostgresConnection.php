<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Pgsql\Schema as PgsqlSchema;

class PostgresConnection extends \Illuminate\Database\PostgresConnection implements ConnectionInterface
{
    use ConnectionExtension;

    public static function checkRequirements()
    {
        if (!extension_loaded('pgsql')) {
            throw new \Exception("Required extension 'pgsql' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver('pgsql');
    }

    public static function getDriverLabel()
    {
        return 'PostgreSQL';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-pgsql.connection.php
        return 'pgsql:host=localhost;port=5432;dbname=db;user=name;password=pwd';
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new PgsqlSchema($this);
        }

        return $this->schemaExtension;
    }
}
