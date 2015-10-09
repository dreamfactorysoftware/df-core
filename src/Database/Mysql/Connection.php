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
                throw new \Exception("Required extension or module 'mysql' is not installed or loaded.");
            } else {
                return false;
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
        return 'mysql:host=localhost;port=3306;dbname=db';
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}