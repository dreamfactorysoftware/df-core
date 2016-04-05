<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Schema\Oci\Schema as OciSchema;

class OracleConnection extends \Yajra\Oci8\Oci8Connection implements ConnectionInterface
{
    use ConnectionExtension;

    public $pdoClass = 'DreamFactory\Core\Database\Oci\PdoAdapter';

    public static function checkRequirements()
    {
        if (!extension_loaded('oci8')) {
            throw new \Exception("Required extension 'oci8' is not detected, but may be compiled in.");
        }
        // don't call parent method here, no need for PDO driver
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

    public static function adaptConfig(array &$config)
    {
        $config['driver'] = 'oci8'; // extension used not PDO driver
        $dsn = isset($config['dsn']) ? $config['dsn'] : null;
        if (!empty($dsn)) {
            $dsn = str_replace(' ', '', $dsn);
            if (!isset($config['host']) && (false !== ($pos = stripos($dsn, 'host=')))) {
                $temp = substr($dsn, $pos + 5);
                $config['host'] = (false !== $pos = stripos($temp, ')')) ? substr($temp, 0, $pos) : $temp;
            }
            if (!isset($config['port']) && (false !== ($pos = stripos($dsn, 'port=')))) {
                $temp = substr($dsn, $pos + 5);
                $config['port'] = (false !== $pos = stripos($temp, ')')) ? substr($temp, 0, $pos) : $temp;
            }
            if (!isset($config['database']) && (false !== ($pos = stripos($dsn, 'sid=')))) {
                $temp = substr($dsn, $pos + 4);
                $config['database'] = (false !== $pos = stripos($temp, ')')) ? substr($temp, 0, $pos) : $temp;
            }
        }

        // laravel database config requires options to be [], not null
        if (array_key_exists('options', $config) && is_null($config['options'])) {
            $config['options'] = [];
        }
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new OciSchema($this);
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

//            switch ( strtoupper( strval( isset($param['param_type']) ? $param['param_type'] : 'IN' ) ) )
//            {
//                case 'INOUT':
//                case 'OUT':
//                default:
            $paramStr .= ":$pName";
//                    break;
//            }
        }

        $sql = "BEGIN $name($paramStr); END;";
        /** @type \PDOStatement $statement */
        $statement = $this->getPdo()->prepare($sql);
        // do binding
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";

//            switch ( strtoupper( strval( isset($param['param_type']) ? $param['param_type'] : 'IN' ) ) )
//            {
//                case 'IN':
//                case 'INOUT':
//                case 'OUT':
//                default:
            $rType = (isset($param['type'])) ? $param['type'] : 'string';
            $rLength = (isset($param['length'])) ? $param['length'] : 256;
            $pdoType = $this->getPdoType($rType);
            $this->bindParam($statement, ":$pName", $params[$key]['value'], $pdoType | \PDO::PARAM_INPUT_OUTPUT, $rLength);
//                    break;
//            }
        }

        // Oracle stored procedures don't return result sets directly, must use OUT parameter.
        try {
            $statement->execute();
        } catch (\Exception $e) {
            $errorInfo = $e instanceof \PDOException ? $e : null;
            $message = $e->getMessage();
            throw new \Exception($message, (int)$e->getCode(), $errorInfo);
        }

        return null;
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
        $sql = "SELECT $name($paramStr) FROM DUAL";
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
