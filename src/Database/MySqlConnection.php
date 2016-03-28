<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Database\Mysql\Schema;

class MySqlConnection extends \Illuminate\Database\MySqlConnection
{
    use ConnectionExtension;

    public $emulatePrepare = true;

    public function checkRequirements()
    {
        if (!extension_loaded('mysql') && !extension_loaded('mysqlnd')) {
            throw new \Exception("Required extension 'mysql' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver('mysql');
    }

    public static function getDriverLabel()
    {
        return 'MySQL';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-mysql.connection.php
        return 'mysql:host=localhost;port=3306;dbname=db;charset=utf8';
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}
