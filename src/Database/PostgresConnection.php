<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Database\Pgsql\Schema;

class PostgresConnection extends \Illuminate\Database\PostgresConnection
{
    use ConnectionExtension;

    public function checkRequirements()
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
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}
