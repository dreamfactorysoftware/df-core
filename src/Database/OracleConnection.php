<?php

namespace DreamFactory\Core\Database;

use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\Driver\OCI8\Driver as DoctrineDriver;
use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Schema\Oci\Schema as OciSchema;
use DreamFactory\Core\Database\Query\Grammars\OracleGrammar as QueryGrammar;
use DreamFactory\Core\Database\Query\OracleBuilder as QueryBuilder;
use DreamFactory\Core\Database\Query\Processors\OracleProcessor as Processor;
use DreamFactory\Core\Database\Schema\Grammars\OracleGrammar as SchemaGrammar;
use DreamFactory\Core\Database\Schema\OracleBuilder as SchemaBuilder;
use DreamFactory\Core\Database\Schema\Sequence;
use DreamFactory\Core\Database\Schema\Trigger;
use Illuminate\Database\Connection;
use Illuminate\Database\Grammar;
use PDO;

class OracleConnection extends Connection implements ConnectionInterface
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

    /**
     * @var string
     */
    protected $schema;

    /**
     * @var \Yajra\Oci8\Schema\Sequence
     */
    protected $sequence;

    /**
     * @var \Yajra\Oci8\Schema\Trigger
     */
    protected $trigger;

    /**
     * @param PDO|\Closure $pdo
     * @param string $database
     * @param string $tablePrefix
     * @param array $config
     */
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        $this->sequence = new Sequence($this);
        $this->trigger  = new Trigger($this);
    }

    /**
     * Set current schema.
     *
     * @param string $schema
     * @return $this
     */
    public function setSchema($schema)
    {
        $this->schema = $schema;
        $sessionVars  = [
            'CURRENT_SCHEMA' => $schema,
        ];

        return $this->setSessionVars($sessionVars);
    }

    /**
     * Update oracle session variables.
     *
     * @param array $sessionVars
     * @return $this
     */
    public function setSessionVars(array $sessionVars)
    {
        $vars = [];
        foreach ($sessionVars as $option => $value) {
            if (strtoupper($option) == 'CURRENT_SCHEMA') {
                $vars[] = "$option  = $value";
            } else {
                $vars[] = "$option  = '$value'";
            }
        }
        $sql = "ALTER SESSION SET " . implode(" ", $vars);
        $this->statement($sql);

        return $this;
    }

    /**
     * Get sequence class.
     *
     * @return \Yajra\Oci8\Schema\Sequence
     */
    public function getSequence()
    {
        return $this->sequence;
    }

    /**
     * Set sequence class.
     *
     * @param \Yajra\Oci8\Schema\Sequence $sequence
     * @return \Yajra\Oci8\Schema\Sequence
     */
    public function setSequence(Sequence $sequence)
    {
        return $this->sequence = $sequence;
    }

    /**
     * Get oracle trigger class.
     *
     * @return \Yajra\Oci8\Schema\Trigger
     */
    public function getTrigger()
    {
        return $this->trigger;
    }

    /**
     * Set oracle trigger class.
     *
     * @param \Yajra\Oci8\Schema\Trigger $trigger
     * @return \Yajra\Oci8\Schema\Trigger
     */
    public function setTrigger(Trigger $trigger)
    {
        return $this->trigger = $trigger;
    }

    /**
     * Get a schema builder instance for the connection.
     *
     * @return \Yajra\Oci8\Schema\OracleBuilder
     */
    public function getSchemaBuilder()
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new SchemaBuilder($this);
    }

    /**
     * Begin a fluent query against a database table.
     *
     * @param  string $table
     * @return \Yajra\Oci8\Query\OracleBuilder
     */
    public function table($table)
    {
        $processor = $this->getPostProcessor();

        $query = new QueryBuilder($this, $this->getQueryGrammar(), $processor);

        return $query->from($table);
    }

    /**
     * Set oracle session date format.
     *
     * @param string $format
     * @return $this
     */
    public function setDateFormat($format = 'YYYY-MM-DD HH24:MI:SS')
    {
        $sessionVars = [
            'NLS_DATE_FORMAT'      => $format,
            'NLS_TIMESTAMP_FORMAT' => $format,
        ];

        return $this->setSessionVars($sessionVars);
    }

    /**
     * Get doctrine connection.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getDoctrineConnection()
    {
        $driver = $this->getDoctrineDriver();

        $data = ['pdo' => $this->getPdo(), 'user' => $this->getConfig('database')];

        return new DoctrineConnection($data, $driver);
    }

    /**
     * Get doctrine driver.
     *
     * @return \Doctrine\DBAL\Driver\OCI8\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return \Yajra\Oci8\Query\Grammars\OracleGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Set the table prefix and return the grammar.
     *
     * @param \Illuminate\Database\Grammar|\Yajra\Oci8\Query\Grammars\OracleGrammar|\Yajra\Oci8\Schema\Grammars\OracleGrammar $grammar
     * @return \Illuminate\Database\Grammar
     */
    public function withTablePrefix(Grammar $grammar)
    {
        return $this->withSchemaPrefix(parent::withTablePrefix($grammar));
    }

    /**
     * Set the schema prefix and return the grammar.
     *
     * @param \Illuminate\Database\Grammar|\Yajra\Oci8\Query\Grammars\OracleGrammar|\Yajra\Oci8\Schema\Grammars\OracleGrammar $grammar
     * @return \Illuminate\Database\Grammar
     */
    public function withSchemaPrefix(Grammar $grammar)
    {
        $grammar->setSchemaPrefix($this->getConfigSchemaPrefix());

        return $grammar;
    }

    /**
     * Get config schema prefix.
     *
     * @return string
     */
    protected function getConfigSchemaPrefix()
    {
        return isset($this->config['prefix_schema']) ? $this->config['prefix_schema'] : '';
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Yajra\Oci8\Schema\Grammars\OracleGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the default post processor instance.
     *
     * @return \Yajra\Oci8\Query\Processors\OracleProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new Processor;
    }
}
