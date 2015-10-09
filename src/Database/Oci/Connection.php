<?php
namespace DreamFactory\Core\Database\Oci;

/**
 * Connection represents a connection to a Oracle database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{
    public $pdoClass = 'DreamFactory\Core\Database\Oci\PdoAdapter';

    public static function checkRequirements($driver, $throw_exception = true)
    {
        if (!extension_loaded('oci8')) {
            if ($throw_exception) {
                throw new \Exception("Required extension or module 'oci8' is not installed or loaded.");
            } else {
                return false;
            }
        }

        // don't call parent method here, no need for PDO driver
        return true;
    }

    public static function getDriverLabel()
    {
        return 'Oracle Database';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-oci.connection.php
        return 'oci:dbname=(DESCRIPTION = (ADDRESS_LIST = (ADDRESS = (PROTOCOL = TCP)(HOST = 192.168.1.1)(PORT = 1521))) (CONNECT_DATA = (SID = db)))';
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}