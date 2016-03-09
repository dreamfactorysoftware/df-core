<?php
namespace DreamFactory\Core\Database\Mysql;

/**
 * Connection represents a connection to a MySQL database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{
    public static function checkRequirements($driver, $throw_exception = true)
    {
        if (!extension_loaded('mysql') && !extension_loaded('mysqlnd')) {
            if ($throw_exception) {
                \Log::notice("Required extension 'mysql' is not detected, but may be compiled in.");
            }
        }

        return parent::checkRequirements('mysql', $throw_exception);
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