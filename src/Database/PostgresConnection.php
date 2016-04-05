<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Schema\Pgsql\Schema as PgsqlSchema;

class PostgresConnection extends \Illuminate\Database\PostgresConnection implements ConnectionInterface
{
    use ConnectionExtension;

    public static function checkRequirements()
    {
        if (!extension_loaded('pgsql')) {
            throw new \Exception("Required extension 'pgsql' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver('pgsql');
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
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new PgsqlSchema($this);
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
        $bindings = [];
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";
            $pValue = (isset($param['value'])) ? $param['value'] : null;

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case 'OUT':
                    // not sent as parameters, but pulled from fetch results
                    break;

                case 'INOUT':
                case 'IN':
                default:
                    $bindings[":$pName"] = $pValue;
                    if (!empty($paramStr)) {
                        $paramStr .= ', ';
                    }
                    $paramStr .= ":$pName";
                    break;
            }
        }

        $sql = "SELECT * FROM $name($paramStr);";
        // driver does not support multiple result sets currently
        $result = $this->select($sql, $bindings);

        // out parameters come back in fetch results, put them in the params for client
        if (isset($result, $result[0])) {
            $temp = (array)$result[0];
            foreach ($params as $key => $param) {
                if (false !== stripos(strval(isset($param['param_type']) ? $param['param_type'] : ''), 'OUT')) {
                    $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";
                    if (isset($temp[$pName])) {
                        $params[$key]['value'] = $temp[$pName];
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
        $sql = "SELECT * FROM $name($paramStr)";

        // driver does not support multiple result sets currently
        $result = $this->select($sql, $bindings);

        return $result;
    }

}
