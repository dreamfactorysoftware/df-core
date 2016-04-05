<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Schema\Mssql\Schema as SqlSrvSchema;

class SqlServerConnection extends \Illuminate\Database\SqlServerConnection implements ConnectionInterface
{
    use ConnectionExtension;

    // These are on by default for sqlsrv driver, but not dblib.
    // Also, can't use 'SET ANSI_DEFAULTS ON', seems to return false positives for DROP TABLE etc. todo
    public $initSQLs = ['SET QUOTED_IDENTIFIER ON;', 'SET ANSI_WARNINGS ON;', 'SET ANSI_NULLS ON;'];

    public static function checkRequirements()
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $driver = 'sqlsrv';
            $extension = 'sqlsrv';
        } else {
            $driver = 'dblib';
            $extension = 'mssql';
        }

        if (!extension_loaded($extension)) {
            throw new \Exception("Required extension '$extension' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver($driver);
    }

    public static function getDriverLabel()
    {
        return 'SQL Server';
    }
    
    public static function getSampleDsn()
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            // http://php.net/manual/en/ref.pdo-sqlsrv.connection.php
            return 'sqlsrv:Server=localhost,1433;Database=db';
        }

        // http://php.net/manual/en/ref.pdo-dblib.connection.php
        return 'dblib:host=localhost:1433;dbname=database;charset=UTF-8';
    }
    
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
        } else {
            if (null !== $dumpLocation = config('df.db.freetds.dump')) {
                if (!putenv("TDSDUMP=$dumpLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMP location.');
                }
            }
            if (null !== $dumpConfLocation = config('df.db.freetds.dumpconfig')) {
                if (!putenv("TDSDUMPCONFIG=$dumpConfLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMPCONFIG location.');
                }
            }
            if (null !== $confLocation = config('df.db.freetds.sqlsrv')) {
                if (!putenv("FREETDSCONF=$confLocation")) {
                    \Log::alert('Could not write environment variable for FREETDSCONF location.');
                }
            }
        }

        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new SqlSrvSchema($this);
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

        $driver = $this->getDriverName();
        if (0 === strcasecmp('sqlsrv', $driver)) {
            return $this->callProcedureSqlsrv($name, $params);
        } else {
            return $this->callProcedureDblib($name, $params);
        }
    }

    protected function callProcedureSqlsrv($name, &$params)
    {
        $paramStr = '';
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";

            if (!empty($paramStr)) {
                $paramStr .= ', ';
            }

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case 'INOUT':
                case 'OUT':
                    $paramStr .= "@$pName=:$pName";
                    break;

                default:
                    $paramStr .= ":$pName";
                    break;
            }
        }

        $sql = "EXEC $name $paramStr;";
        /** @type \PDOStatement $statement */
        $statement = $this->getPdo()->prepare($sql);

        // do binding
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";
            if (!isset($param['value'])) {
                $param['value'] = null;
            }

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case '':
                case 'IN':
                    $this->bindValue($statement, ":$pName", $param['value']);
                    break;
                case 'INOUT':
                case 'OUT':
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

        return $result;
    }

    protected function callProcedureDblib($name, &$params)
    {
        // Note that using the dblib driver doesn't allow binding of output parameters,
        // and also requires declaration prior to and selecting after to retrieve them.
        $paramStr = '';
        $pre = '';
        $post = '';
        $skip = 0;
        $bindings = [];
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? $param['name'] : "p$key";
            $pValue = (isset($param['value'])) ? $param['value'] : null;

            if (!empty($paramStr)) {
                $paramStr .= ', ';
            }

            switch (strtoupper(strval(isset($param['param_type']) ? $param['param_type'] : 'IN'))) {
                case 'INOUT':
                    // with dblib driver you can't bind output parameters
                    $rType = $param['type'];
                    $pre .= "DECLARE @$pName $rType; SET @$pName = $pValue;";
                    $skip++;
                    $post .= "SELECT @$pName AS [$pName];";
                    $paramStr .= "@$pName OUTPUT";
                    break;

                case 'OUT':
                    // with dblib driver you can't bind output parameters
                    $rType = $param['type'];
                    $pre .= "DECLARE @$pName $rType;";
                    $post .= "SELECT @$pName AS [$pName];";
                    $paramStr .= "@$pName OUTPUT";
                    break;

                default:
                    $bindings[":$pName"] = $pValue;
                    $paramStr .= ":$pName";
                    break;
            }
        }

        $sql = "$pre EXEC $name $paramStr; $post";
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
        for ($i = 0; $i < $skip; $i++) {
            if ($reader->nextResult()) {
                $result = $reader->readAll();
            }
        }
        if ($reader->nextResult()) {
            // more data coming, make room
            $result = [$result];
            do {
                $temp = $reader->readAll();
                $keep = true;
                if (1 == count($temp)) {
                    $check = current($temp);
                    foreach ($params as &$param) {
                        $pName = (isset($param['name'])) ? $param['name'] : '';
                        if (isset($check[$pName])) {
                            $param['value'] = $check[$pName];
                            $keep = false;
                        }
                    }
                }
                if ($keep) {
                    $result[] = $temp;
                }
            } while ($reader->nextResult());

            // if there is only one data set, just return it
            if (1 == count($result)) {
                $result = $result[0];
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
        if (false === strpos($name, '.')) {
            // requires full name with schema here.
            $name = $this->getSchema()->getDefaultSchema() . '.' . $name;
        }
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
