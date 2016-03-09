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
                \Log::notice("Required extension 'oci8' is not detected, but may be compiled in.");
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