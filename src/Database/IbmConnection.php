<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Database\Schema\Ibmdb2\Schema as IbmSchema;

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

    /**
     * @param string $name
     * @param array  $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function callProcedure($name, &$params)
    {
        $name = $this->quoteTableName($name);
        $paramStr = '';
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";

            if (!empty($paramStr)) {
                $paramStr .= ', ';
            }

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case 'OUT':
                case 'INOUT':
                case 'IN':
                default:
                    $paramStr .= ":$pName";
                    break;
            }
        }

        $sql = "CALL $name($paramStr)";
        /** @type \PDOStatement $statement */
        $statement = $this->getPdo()->prepare($sql);
        // do binding
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case 'OUT':
                case 'INOUT':
                case 'IN':
                default:
                    $rType = (isset($param['type'])) ? $param['type'] : 'string';
                    $rLength = (isset($param['length'])) ? $param['length'] : 256;
                    $pdoType = $this->getPdoType($rType);
                    $this->bindParam($statement, ":$pName", $params[$key]['value'], $pdoType | \PDO::PARAM_INPUT_OUTPUT,
                        $rLength);
                    break;
            }
        }

        // support multiple result sets
        try {
            $statement->execute();
            $reader = new DataReader($statement);
        } catch (\Exception $e) {
            $errorInfo = $e instanceof \PDOException ? $e : null;
            $message = $e->getMessage();
            throw new \Exception($message, (int)$e->getCode(), $errorInfo);
        }
        $result = $reader->readAll();
        if ($reader->nextResult()) {
            // more data coming, make room
            $result = [$result];
            do {
                $result[] = $reader->readAll();
            } while ($reader->nextResult());
        }

        // out parameters come back in fetch results, put them in the params for client
        if (isset($result, $result[0])) {
            foreach ($params as $key => $param) {
                if (false !== stripos(strval(isset($param['param_type']) ? $param['param_type'] : ''), 'OUT')) {
                    $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";
                    if (isset($result[0][$pName])) {
                        $params[$key]['value'] = $result[0][$pName];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callFunction($name, &$params)
    {
        $name = $this->quoteTableName($name);
        $bindings = [];
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? ':' . $param['name'] : ":p$key";
            $pValue = isset($param['value']) ? $param['value'] : null;

            $bindings[$pName] = $pValue;
        }

        $paramStr = implode(',', array_keys($bindings));
//        $sql = "SELECT * from TABLE($name($paramStr))";
        $sql = "SELECT $name($paramStr) FROM SYSIBM.SYSDUMMY1";
        /** @type \PDOStatement $statement */
        $statement = $this->getPdo()->prepare($sql);

        // do binding
        $this->bindValues($statement, $bindings);

        // support multiple result sets
        try {
            $statement->execute();
            $reader = new DataReader($statement);
        } catch (\Exception $e) {
            $errorInfo = $e instanceof \PDOException ? $e : null;
            $message = $e->getMessage();
            throw new \Exception($message, (int)$e->getCode(), $errorInfo);
        }
        $result = $reader->readAll();
        if ($reader->nextResult()) {
            // more data coming, make room
            $result = [$result];
            do {
                $result[] = $reader->readAll();
            } while ($reader->nextResult());
        }

        return $result;
    }
}
