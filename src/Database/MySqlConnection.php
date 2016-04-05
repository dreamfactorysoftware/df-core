<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Schema\Mysql\Schema as MysqlSchema;

class MySqlConnection extends \Illuminate\Database\MySqlConnection implements ConnectionInterface
{
    use ConnectionExtension;

    public $emulatePrepare = true;

    public static function checkRequirements()
    {
        if (!extension_loaded('mysql') && !extension_loaded('mysqlnd')) {
            throw new \Exception("Required extension 'mysql' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver('mysql');
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
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new MysqlSchema($this);
        }

        return $this->schemaExtension;
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callProcedure($name, &$params)
    {
        $name = $this->quoteTableName($name);
        $paramStr = '';
        $pre = '';
        $post = '';
        $bindings = [];
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";
            $pValue = isset($param['value']) ? $param['value'] : null;

            if (!empty($paramStr)) {
                $paramStr .= ', ';
            }

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case 'INOUT':
                    // not using binding for out or inout params here due to earlier (<5.5.3) mysql library bug
                    // since binding isn't working, set the values via statements, get the values via select
                    $pre .= "SET @$pName = $pValue; ";
                    $post .= (empty($post)) ? "SELECT @$pName" : ", @$pName";
                    $paramStr .= "@$pName";
                    break;

                case 'OUT':
                    // not using binding for out or inout params here due to earlier (<5.5.3) mysql library bug
                    // since binding isn't working, get the values via select
                    $post .= (empty($post)) ? "SELECT @$pName" : ", @$pName";
                    $paramStr .= "@$pName";
                    break;

                default:
                    $bindings[":$pName"] = $pValue;
                    $paramStr .= ":$pName";
                    break;
            }
        }

        !empty($pre) && $this->statement($pre);

        $sql = "CALL $name($paramStr)";
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
        $result = [];
        try {
            do {
                $result[] = $reader->readAll();
            } while ($reader->nextResult());
        } catch (\Exception $ex) {
            // mysql via pdo has issue of nextRowSet returning true one too many times
            if (false !== strpos($ex->getMessage(), 'General Error')) {
                throw $ex;
            }

            // if there is only one data set, just return it
            if (1 == count($result)) {
                $result = $result[0];
            }
        }

        if (!empty($post)) {
            $out = $this->selectOne($post . ';');
            foreach ($params as $key => &$param) {
                $pName = '@' . $param['name'];
                switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                    case 'INOUT':
                    case 'OUT':
                        if (isset($out, $out[$pName])) {
                            $param['value'] = $out[$pName];
                        }
                        break;
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
        $sql = "SELECT $name($paramStr);";
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
        $result = [];
        try {
            do {
                $result[] = $reader->readAll();
            } while ($reader->nextResult());
        } catch (\Exception $ex) {
            // mysql via pdo has issue of nextRowSet returning true one too many times
            if (false !== strpos($ex->getMessage(), 'General Error')) {
                throw $ex;
            }

            // if there is only one data set, just return it
            if (1 == count($result)) {
                $result = $result[0];
            }
        }

        return $result;
    }

}
