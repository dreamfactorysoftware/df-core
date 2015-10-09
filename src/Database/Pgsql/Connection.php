<?php
namespace DreamFactory\Core\Database\Pgsql;

/**
 * Connection represents a connection to a PostgreSQL database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{
    public static function checkRequirements($driver, $throw_exception = true)
    {
        if (!extension_loaded('pgsql')) {
            if ($throw_exception) {
                throw new \Exception("Required extension or module 'pgsql' is not installed or loaded.");
            } else {
                return false;
            }
        }

        return parent::checkRequirements('pgsql', $throw_exception);
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