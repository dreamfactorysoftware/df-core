<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\SchemaInterface;

/**
 * ConnectionExtension represents a connection to a database with DreamFactory extensions.
 *
 */
trait ConnectionExtension
{
    /**
     * @var array
     */
    protected $attributes = [];
    /**
     * @var SchemaInterface
     */
    protected $schemaExtension;

    /**
     * Returns the database schema for the current connection
     *
     * @throws \Exception if Connection does not support reading schema for specified database driver
     * @return SchemaInterface the database schema for the current connection
     */
    abstract public function getSchema();

    public static function getDriverLabel()
    {
        return 'Unknown';
    }

    public static function getSampleDsn()
    {
        return '';
    }

    /**
     * @throws \Exception
     */
    public static function checkRequirements()
    {
    }

    /**
     * @param string $driver
     *
     * @throws \Exception
     */
    public static function checkForPdoDriver($driver)
    {
        if (!extension_loaded('PDO')) {
            throw new \Exception("Required PDO extension is not installed or loaded.");
        }

        // see overrides for specific driver checks
        $drivers = \PDO::getAvailableDrivers();
        if (!in_array($driver, $drivers)) {
            throw new \Exception("Required PDO driver '$driver' is not installed or loaded properly.");
        }
    }

    public static function adaptConfig(array &$config)
    {
        $dsn = isset($config['dsn']) ? $config['dsn'] : null;
        if (!empty($dsn)) {
            if (!isset($config['host']) && (false !== ($pos = strpos($dsn, 'host=')))) {
                $temp = substr($dsn, $pos + 5);
                $config['host'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            }
            if (!isset($config['port']) && (false !== ($pos = strpos($dsn, 'port=')))) {
                $temp = substr($dsn, $pos + 5);
                $config['port'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            }
            if (!isset($config['database']) && (false !== ($pos = strpos($dsn, 'dbname=')))) {
                $temp = substr($dsn, $pos + 7);
                $config['database'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
            }
            if (!isset($config['charset'])) {
                if (false !== ($pos = strpos($dsn, 'charset='))) {
                    $temp = substr($dsn, $pos + 8);
                    $config['charset'] = (false !== $pos = strpos($temp, ';')) ? substr($temp, 0, $pos) : $temp;
                } else {
                    $config['charset'] = 'utf8';
                }
            }
        }

        // must be there
        if (!array_key_exists('database', $config)) {
            $config['database'] = null;
        }

        // must be there
        if (!array_key_exists('prefix', $config)) {
            $config['prefix'] = null;
        }

        if (!isset($config['collation'])) {
            $config['collation'] = 'utf8_unicode_ci';
        }

        // laravel database config requires options to be [], not null
        if (array_key_exists('options', $config) && is_null($config['options'])) {
            $config['options'] = [];
        }
    }

    /**
     * Returns the name of the DB driver from a connection string
     *
     * @param string $dsn The connection string
     *
     * @return string name of the DB driver
     */
    public static function getDriverFromDSN($dsn)
    {
        if (is_string($dsn)) {
            if (($pos = strpos($dsn, ':')) !== false) {
                return strtolower(substr($dsn, 0, $pos));
            }
        }

        return null;
    }

    public function getUserName()
    {
        return $this->getConfig('username');
    }

    public function selectColumn($query, $bindings = [], $useReadPdo = true, $column = null)
    {
        $rows = $this->select($query, $bindings, $useReadPdo);
        foreach ($rows as $key => $row) {
            if (!empty($column)) {
                $rows[$key] = data_get($row, $column);
            } else {
                $row = (array)$row;
                $rows[$key] = reset($row);
            }
        }

        return $rows;
    }

    public function selectValue($query, $bindings = [], $column = null)
    {
        if (null !== $row = $this->selectOne($query, $bindings)) {
            if (!empty($column)) {
                return data_get($row, $column);
            } else {
                $row = (array)$row;

                return reset($row);
            }
        }

        return null;
    }

    /**
     * Quotes a string value for use in a query.
     *
     * @param string $str string to be quoted
     *
     * @return string the properly quoted string
     * @see http://www.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue($str)
    {
        if (is_int($str) || is_float($str)) {
            return $str;
        }

        if (($value = $this->getPdo()->quote($str)) !== false) {
            return $value;
        } else  // the driver doesn't support quote (e.g. oci)
        {
            return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
        }
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     *
     * @param string $name table name
     *
     * @return string the properly quoted table name
     */
    public function quoteTableName($name)
    {
        return $this->getSchema()->quoteTableName($name);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     *
     * @param string $name column name
     *
     * @return string the properly quoted column name
     */
    public function quoteColumnName($name)
    {
        return $this->getSchema()->quoteColumnName($name);
    }

    /**
     * Determines the PDO type for the specified PHP type.
     *
     * @param string $type The PHP type (obtained by gettype() call).
     *
     * @return integer the corresponding PDO type
     */
    public function getPdoType($type)
    {
        static $map = [
            'boolean'  => \PDO::PARAM_BOOL,
            'integer'  => \PDO::PARAM_INT,
            'string'   => \PDO::PARAM_STR,
            'resource' => \PDO::PARAM_LOB,
            'NULL'     => \PDO::PARAM_NULL,
        ];

        return isset($map[$type]) ? $map[$type] : \PDO::PARAM_STR;
    }

    /**
     * Returns the case of the column names
     *
     * @return mixed the case of the column names
     * @see http://www.php.net/manual/en/pdo.setattribute.php
     */
    public function getColumnCase()
    {
        return $this->getAttribute(\PDO::ATTR_CASE);
    }

    /**
     * Sets the case of the column names.
     *
     * @param mixed $value the case of the column names
     *
     * @see http://www.php.net/manual/en/pdo.setattribute.php
     */
    public function setColumnCase($value)
    {
        $this->setAttribute(\PDO::ATTR_CASE, $value);
    }

    /**
     * Returns how the null and empty strings are converted.
     *
     * @return mixed how the null and empty strings are converted
     * @see http://www.php.net/manual/en/pdo.setattribute.php
     */
    public function getNullConversion()
    {
        return $this->getAttribute(\PDO::ATTR_ORACLE_NULLS);
    }

    /**
     * Sets how the null and empty strings are converted.
     *
     * @param mixed $value how the null and empty strings are converted
     *
     * @see http://www.php.net/manual/en/pdo.setattribute.php
     */
    public function setNullConversion($value)
    {
        $this->setAttribute(\PDO::ATTR_ORACLE_NULLS, $value);
    }

    /**
     * Returns whether creating or updating a DB record will be automatically committed.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @return boolean whether creating or updating a DB record will be automatically committed.
     */
    public function getAutoCommit()
    {
        return $this->getAttribute(\PDO::ATTR_AUTOCOMMIT);
    }

    /**
     * Sets whether creating or updating a DB record will be automatically committed.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @param boolean $value whether creating or updating a DB record will be automatically committed.
     */
    public function setAutoCommit($value)
    {
        $this->setAttribute(\PDO::ATTR_AUTOCOMMIT, $value);
    }

    /**
     * Returns whether the connection is persistent or not.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @return boolean whether the connection is persistent or not
     */
    public function getPersistent()
    {
        return $this->getAttribute(\PDO::ATTR_PERSISTENT);
    }

    /**
     * Sets whether the connection is persistent or not.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @param boolean $value whether the connection is persistent or not
     */
    public function setPersistent($value)
    {
        $this->setAttribute(\PDO::ATTR_PERSISTENT, $value);
    }

    /**
     * Returns the version information of the DB driver.
     *
     * @return string the version information of the DB driver
     */
    public function getClientVersion()
    {
        return $this->getAttribute(\PDO::ATTR_CLIENT_VERSION);
    }

    /**
     * Returns the status of the connection.
     * Some DBMS (such as sqlite) may not support this feature.
     *
     * @return string the status of the connection
     */
    public function getConnectionStatus()
    {
        return $this->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    }

    /**
     * Returns whether the connection performs data prefetching.
     *
     * @return boolean whether the connection performs data prefetching
     */
    public function getPrefetch()
    {
        return $this->getAttribute(\PDO::ATTR_PREFETCH);
    }

    /**
     * Returns the information of DBMS server.
     *
     * @return string the information of DBMS server
     */
    public function getServerInfo()
    {
        return $this->getAttribute(\PDO::ATTR_SERVER_INFO);
    }

    /**
     * Returns the version information of DBMS server.
     *
     * @return string the version information of DBMS server
     */
    public function getServerVersion()
    {
        return $this->getAttribute(\PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Returns the timeout settings for the connection.
     *
     * @return integer timeout settings for the connection
     */
    public function getTimeout()
    {
        return $this->getAttribute(\PDO::ATTR_TIMEOUT);
    }

    /**
     * Obtains a specific DB connection attribute information.
     *
     * @param integer $name the attribute to be queried
     *
     * @return mixed the corresponding attribute information
     * @see http://www.php.net/manual/en/function.PDO-getAttribute.php
     */
    public function getAttribute($name)
    {
        return $this->getPdo()->getAttribute($name);
    }

    /**
     * Sets an attribute on the database connection.
     *
     * @param integer $name  the attribute to be set
     * @param mixed   $value the attribute value
     *
     * @see http://www.php.net/manual/en/function.PDO-setAttribute.php
     */
    public function setAttribute($name, $value)
    {
        $pdo = $this->getPdo();
        if ($pdo instanceof \PDO) {
            $pdo->setAttribute($name, $value);
        } else {
            $this->attributes[$name] = $value;
        }
    }

    /**
     * Returns the attributes that are previously explicitly set for the DB connection.
     *
     * @return array attributes (name=>value) that are previously explicitly set for the DB connection.
     * @see   setAttributes
     * @since 1.1.7
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets a set of attributes on the database connection.
     *
     * @param array $values attributes (name=>value) to be set.
     *
     * @see   setAttribute
     * @since 1.1.7
     */
    public function setAttributes($values)
    {
        foreach ($values as $name => $value) {
            $this->attributes[$name] = $value;
        }
    }

    /**
     * @return boolean
     */
    public function supportsFunctions()
    {
        return true;
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @throws \Exception
     * @return mixed
     */
    public function callFunction(
        /** @noinspection PhpUnusedParameterInspection */
        $name, &$params)
    {
        if (!$this->supportsFunctions()) {
            throw new \Exception('Stored Functions are not supported by this database connection.');
        }
    }

    /**
     * @return boolean
     */
    public function supportsProcedures()
    {
        return true;
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
        if (!$this->supportsProcedures()) {
            throw new \Exception('Stored Procedures are not supported by this database connection.');
        }

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
     * @param \PDOStatement $statement
     * @param               $name
     * @param               $value
     * @param null          $dataType
     * @param null          $length
     * @param null          $driverOptions
     */
    public function bindParam($statement, $name, &$value, $dataType = null, $length = null, $driverOptions = null)
    {
        if ($dataType === null) {
            $statement->bindParam($name, $value, $this->getPdoType(gettype($value)));
        } elseif ($length === null) {
            $statement->bindParam($name, $value, $dataType);
        } elseif ($driverOptions === null) {
            $statement->bindParam($name, $value, $dataType, $length);
        } else {
            $statement->bindParam($name, $value, $dataType, $length, $driverOptions);
        }
    }

    /**
     * Binds a value to a parameter.
     *
     * @param \PDOStatement $statement
     * @param mixed         $name     Parameter identifier. For a prepared statement
     *                                using named placeholders, this will be a parameter name of
     *                                the form :name. For a prepared statement using question mark
     *                                placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed         $value    The value to bind to the parameter
     * @param integer       $dataType SQL data type of the parameter. If null, the type is determined by the PHP type
     *                                of the value.
     *
     * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($statement, $name, $value, $dataType = null)
    {
        if ($dataType === null) {
            $statement->bindValue($name, $value, $this->getPdoType(gettype($value)));
        } else {
            $statement->bindValue($name, $value, $dataType);
        }
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to {@link bindValue} except that it binds multiple values.
     * Note that the SQL data type of each value is determined by its PHP type.
     *
     * @param \PDOStatement $statement
     * @param array         $values the values to be bound. This must be given in terms of an associative
     *                              array with array keys being the parameter names, and array values the corresponding
     *                              parameter values. For example, <code>array(':name'=>'John', ':age'=>25)</code>.
     */
    public function bindValues($statement, $values)
    {
        foreach ($values as $name => $value) {
            $statement->bindValue($name, $value, $this->getPdoType(gettype($value)));
        }
    }
}
