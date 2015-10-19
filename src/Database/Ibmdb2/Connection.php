<?php
namespace DreamFactory\Core\Database\Ibmdb2;

/**
 * Connection represents a connection to a IBM DB2 database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{
    public static function checkRequirements($driver, $throw_exception = true)
    {
        if (!extension_loaded('ibm_db2')) {
            if ($throw_exception) {
                throw new \Exception("Required extension or module 'ibm_db2' is not installed or loaded.");
            } else {
                return false;
            }
        }

        return parent::checkRequirements('ibm', $throw_exception);
    }

    public static function getDriverLabel()
    {
        return 'IBM DB2';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-ibm.connection.php
        return 'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=db;HOSTNAME=localhost;PORT=56789;PROTOCOL=TCPIP;';
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }

//    protected function initConnection($pdo)
//    {
//        parent::initConnection($pdo);
//        $this->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
//        $this->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
//    }
//
//    public function getPdoType($type)
//    {
//        if ($type == 'NULL') {
//            return \PDO::PARAM_STR;
//        } else {
//            return parent::getPdoType($type);
//        }
//    }
}