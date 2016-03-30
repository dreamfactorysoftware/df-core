<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Database\Ibmdb2\Schema as IbmSchema;

class IbmConnection
{
    use ConnectionExtension;

    public static function checkRequirements()
    {
        if (!extension_loaded('ibm_db2')) {
            throw new \Exception("Required extension 'ibm_db2' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver('ibm');
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
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new IbmSchema($this);
        }

        return $this->schemaExtension;
    }
}
