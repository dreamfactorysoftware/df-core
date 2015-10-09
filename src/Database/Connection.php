<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\CacheInterface;

/**
 * Connection represents a connection to a database.
 *
 * Connection works together with {@link Command}, {@link DataReader}
 * and {@link Transaction} to provide data access to various DBMS
 * in a common set of APIs. They are a thin wrapper of the {@link http://www.php.net/manual/en/ref.pdo.php PDO}
 * PHP extension.
 *
 * To establish a connection, set {@link setActive active} to true after
 * specifying {@link connectionString}, {@link username} and {@link password}.
 *
 * The following example shows how to create a Connection instance and establish
 * the actual connection:
 * <pre>
 * $connection=new Connection($dsn,$username,$password);
 * $connection->active=true;
 * </pre>
 *
 * After the DB connection is established, one can execute an SQL statement like the following:
 * <pre>
 * $command=$connection->createCommand($sqlStatement);
 * $command->execute();   // a non-query SQL statement execution
 * // or execute an SQL query and fetch the result set
 * $reader=$command->query();
 *
 * // each $row is an array representing a row of data
 * foreach($reader as $row) ...
 * </pre>
 *
 * One can do prepared SQL execution and bind parameters to the prepared SQL:
 * <pre>
 * $command=$connection->createCommand($sqlStatement);
 * $command->bindParam($name1,$value1);
 * $command->bindParam($name2,$value2);
 * $command->execute();
 * </pre>
 *
 * To use transaction, do like the following:
 * <pre>
 * $transaction=$connection->beginTransaction();
 * try
 * {
 *    $connection->createCommand($sql1)->execute();
 *    $connection->createCommand($sql2)->execute();
 *    //.... other SQL executions
 *    $transaction->commit();
 * }
 * catch(Exception $e)
 * {
 *    $transaction->rollback();
 * }
 * </pre>
 *
 * Connection also provides a set of methods to support setting and querying
 * of certain DBMS attributes, such as {@link getNullConversion nullConversion}.
 *
 * Since Connection implements the interface IApplicationComponent, it can
 * be used as an application component and be configured in application configuration,
 * like the following,
 * <pre>
 * array(
 *     'components'=>array(
 *         'db'=>array(
 *             'class'=>'Connection',
 *             'connectionString'=>'sqlite:path/to/dbfile',
 *         ),
 *     ),
 * )
 * </pre>
 *
 * @property boolean        $active             Whether the DB connection is established.
 * @property \PDO           $pdoInstance        The PDO instance, null if the connection is not established yet.
 * @property Transaction    $currentTransaction The currently active transaction. Null if no active transaction.
 * @property Schema         $schema             The database schema for the current connection.
 * @property CommandBuilder $commandBuilder     The command builder.
 * @property string         $lastInsertID       The row ID of the last row inserted, or the last value retrieved
 *           from the sequence object.
 * @property mixed          $columnCase         The case of the column names.
 * @property mixed          $nullConversion     How the null and empty strings are converted.
 * @property boolean        $autoCommit         Whether creating or updating a DB record will be automatically
 *           committed.
 * @property boolean        $persistent         Whether the connection is persistent or not.
 * @property string         $driverName         Name of the DB driver.
 * @property string         $clientVersion      The version information of the DB driver.
 * @property string         $connectionStatus   The status of the connection.
 * @property boolean        $prefetch           Whether the connection performs data prefetching.
 * @property string         $serverInfo         The information of DBMS server.
 * @property string         $serverVersion      The version information of DBMS server.
 * @property integer        $timeout            Timeout settings for the connection.
 * @property array          $attributes         Attributes (name=>value) that are previously explicitly set for the
 *           DB connection.
 * @property array          $stats              The first element indicates the number of SQL statements executed,
 * and the second element the total time spent in SQL execution.
 */
abstract class Connection
{
    /**
     * @var string The Data Source Name, or DSN, contains the information required to connect to the database.
     * @see http://www.php.net/manual/en/function.PDO-construct.php
     *
     * Note that if you're using GBK or BIG5 then it's highly recommended to
     * update to PHP 5.3.6+ and to specify charset via DSN like
     * 'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
     */
    public $connectionString;
    /**
     * @var string the username for establishing DB connection. Defaults to empty string.
     */
    public $username = '';
    /**
     * @var string the password for establishing DB connection. Defaults to empty string.
     */
    public $password = '';
    /**
     * @var boolean whether the database connection should be automatically established
     * the component is being initialized. Defaults to true. Note, this property is only
     * effective when the Connection object is used as an application component.
     */
    public $autoConnect = true;
    /**
     * @var string the charset used for database connection. The property is only used
     * for MySQL and PostgreSQL databases. Defaults to null, meaning using default charset
     * as specified by the database.
     *
     * Note that if you're using GBK or BIG5 then it's highly recommended to
     * update to PHP 5.3.6+ and to specify charset via DSN like
     * 'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'.
     */
    public $charset;
    /**
     * @var boolean whether to turn on prepare emulation. Defaults to false, meaning PDO
     * will use the native prepare support if available. For some databases (such as MySQL),
     * this may need to be set true so that PDO can emulate the prepare support to bypass
     * the buggy native prepare support. Note, this property is only effective for PHP 5.1.3 or above.
     * The default value is null, which will not change the ATTR_EMULATE_PREPARES value of PDO.
     */
    public $emulatePrepare;
    /**
     * @var string the default prefix for table names. Defaults to null, meaning no table prefix.
     * By setting this property, any token like '{{tableName}}' in {@link Command::text} will
     * be replaced by 'prefixTableName', where 'prefix' refers to this property value.
     */
    public $tablePrefix;
    /**
     * @var array list of SQL statements that should be executed right after the DB connection is established.
     */
    public $initSQLs;
    /**
     * @var string Custom PDO wrapper class.
     */
    public $pdoClass = 'PDO';
    /**
     * @var CacheInterface
     */
    public $cache = null;
    /**
     * @var DbExtrasInterface
     */
    public $extraStore = null;
    /**
     * @var boolean
     */
    protected $defaultSchemaOnly = false;
    /**
     * @var array
     */
    protected $attributes = [];
    /**
     * @var boolean.
     */
    protected $active = false;
    /**
     * @var \PDO
     */
    protected $pdo;
    /**
     * @var Transaction
     */
    protected $transaction;
    /**
     * @var Schema
     */
    protected $schema;

    public static function getDriverLabel()
    {
        return 'Unknown';
    }

    public static function getSampleDsn()
    {
        return '';
    }

    public static function checkRequirements($driver, $throw_exception = true)
    {
        if (!extension_loaded('PDO')) {
            if ($throw_exception) {
                throw new \Exception("Required PDO extension is not installed or loaded.");
            } else {
                return false;
            }
        }

        // see overrides for specific driver checks
        $drivers = \PDO::getAvailableDrivers();
        if (!in_array($driver, $drivers)) {
            if ($throw_exception) {
                throw new \Exception("Required PDO driver '$driver' is not installed or loaded properly.");
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Constructor.
     * Note, the DB connection is not established when this connection
     * instance is created. Set {@link setActive active} property to true
     * to establish the connection.
     *
     * @param string $dsn                         The Data Source Name, or DSN, contains the information required to
     *                                            connect to the database.
     * @param string $username                    The user name for the DSN string.
     * @param string $password                    The password for the DSN string.
     *
     * @see http://www.php.net/manual/en/function.PDO-construct.php
     */
    public function __construct($dsn = '', $username = '', $password = '')
    {
        $this->connectionString = $dsn;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Close the connection when serializing.
     *
     * @return array
     */
    public function __sleep()
    {
        $this->close();

        return array_keys(get_object_vars($this));
    }

    /**
     * @return CacheInterface|null
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @param CacheInterface|null $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     */
    public function flushCache()
    {
        if ($this->cache) {
            $this->cache->flush();
        }
    }

    /**
     * @return DbExtrasInterface|null
     */
    public function getExtraStore()
    {
        return $this->extraStore;
    }

    /**
     * @param DbExtrasInterface|null $extraStore
     */
    public function setExtraStore($extraStore)
    {
        $this->extraStore = $extraStore;
    }

    /**
     * @return boolean
     */
    public function isDefaultSchemaOnly()
    {
        return $this->defaultSchemaOnly;
    }

    /**
     * @param boolean $defaultSchemaOnly
     */
    public function setDefaultSchemaOnly($defaultSchemaOnly)
    {
        $this->defaultSchemaOnly = $defaultSchemaOnly;
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

    /**
     * Initializes the component.
     * This method is required by {@link IApplicationComponent} and is invoked by application
     * when the Connection is used as an application component.
     * If you override this method, make sure to call the parent implementation
     * so that the component can be marked as initialized.
     */
    public function init()
    {
        if ($this->autoConnect) {
            $this->setActive(true);
        }
    }

    /**
     * Returns whether the DB connection is established.
     *
     * @return boolean whether the DB connection is established
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * Open or close the DB connection.
     *
     * @param boolean $value whether to open or close DB connection
     *
     * @throws \Exception if connection fails
     */
    public function setActive($value)
    {
        if ($value != $this->active) {
            if ($value) {
                $this->open();
            } else {
                $this->close();
            }
        }
    }

    /**
     * Opens DB connection if it is currently not
     *
     * @throws \Exception if connection fails
     */
    protected function open()
    {
        if ($this->pdo === null) {
            if (empty($this->connectionString)) {
                throw new \Exception('Connection.connectionString cannot be empty.');
            }
            try {
                $this->pdo = $this->createPdoInstance();
                $this->initConnection($this->pdo);
                $this->active = true;
            } catch (\PDOException $e) {
                throw new \Exception(
                    'Connection failed to open the DB connection: ' . $e->getMessage(), (int)$e->getCode(),
                    $e->errorInfo
                );
            }
        }
    }

    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    protected function close()
    {
        $this->pdo = null;
        $this->active = false;
        $this->schema = null;
    }

    /**
     * Creates the PDO instance.
     * When some functionalities are missing in the pdo driver, we may use
     * an adapter class to provide them.
     *
     * @throws \Exception when failed to open DB connection
     * @return \PDO the pdo instance
     */
    protected function createPdoInstance()
    {
        $pdoClass = $this->pdoClass;
        if (!class_exists($pdoClass)) {
            throw new \Exception("Connection is unable to find PDO class '{$pdoClass}'. Make sure PDO is installed correctly.");
        }

        @$instance = new $pdoClass($this->connectionString, $this->username, $this->password, $this->attributes);

        if (!$instance) {
            throw new \Exception('Connection failed to open the DB connection.');
        }

        return $instance;
    }

    /**
     * Initializes the open db connection.
     * This method is invoked right after the db connection is established.
     * The default implementation is to set the charset for MySQL and PostgreSQL database connections.
     *
     * @param \PDO $pdo the PDO instance
     */
    protected function initConnection($pdo)
    {
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if ($this->emulatePrepare !== null && constant('PDO::ATTR_EMULATE_PREPARES')) {
            $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $this->emulatePrepare);
        }

        $driver = strtolower($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        if ($this->charset !== null) {
            if (in_array($driver, ['pgsql', 'mysql', 'mysqli'])) {
                $pdo->exec('SET NAMES ' . $pdo->quote($this->charset));
            }
        }
        if (is_array($this->initSQLs)) {
            foreach ($this->initSQLs as $sql) {
                $pdo->exec($sql);
            }
        }
    }

    /**
     * Returns the PDO instance.
     *
     * @return \PDO the PDO instance, null if the connection is not established yet
     */
    public function getPdoInstance()
    {
        return $this->pdo;
    }

    /**
     * Creates a command for execution.
     *
     * @param mixed $query the DB query to be executed. This can be either a string representing a SQL statement,
     *                     or an array representing different fragments of a SQL statement. Please refer to {@link
     *                     Command::__construct} for more details about how to pass an array as the query. If this
     *                     parameter is not given, you will have to call query builder methods of {@link Command} to
     *                     build the DB query.
     *
     * @return Command the DB command
     */
    public function createCommand($query = null)
    {
        $this->setActive(true);

        return new Command($this, $query);
    }

    /**
     * Returns the currently active transaction.
     *
     * @return Transaction the currently active transaction. Null if no active transaction.
     */
    public function getCurrentTransaction()
    {
        if ($this->transaction !== null) {
            if ($this->transaction->getActive()) {
                return $this->transaction;
            }
        }

        return null;
    }

    /**
     * Starts a transaction.
     *
     * @return Transaction the transaction initiated
     */
    public function beginTransaction()
    {
        $this->setActive(true);
        $this->pdo->beginTransaction();

        return $this->transaction = new Transaction($this);
    }

    /**
     * Returns the database schema for the current connection
     *
     * @throws \Exception if Connection does not support reading schema for specified database driver
     * @return Schema the database schema for the current connection
     */
    abstract public function getSchema();

    /**
     * Returns the SQL command builder for the current DB connection.
     *
     * @return CommandBuilder the command builder
     */
    public function getCommandBuilder()
    {
        return $this->getSchema()->getCommandBuilder();
    }

    /**
     * Returns the ID of the last inserted row or sequence value.
     *
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     *
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @see http://www.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function getLastInsertID($sequenceName = '')
    {
        $this->setActive(true);

        return $this->pdo->lastInsertId($sequenceName);
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

        $this->setActive(true);
        if (($value = $this->pdo->quote($str)) !== false) {
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
     * Returns the name of the DB driver
     *
     * @return string name of the DB driver
     */
    public function getDBName()
    {
        if (($pos = strpos($this->connectionString, ':')) !== false) {
            return strtolower(substr($this->connectionString, 0, $pos));
        }

        return $this->getAttribute(\PDO::ATTR_DRIVER_NAME);
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
        $this->setActive(true);

        return $this->pdo->getAttribute($name);
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
        if ($this->pdo instanceof \PDO) {
            $this->pdo->setAttribute($name, $value);
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
     * Returns the statistical results of SQL executions.
     * The results returned include the number of SQL statements executed and
     * the total time spent.
     * In order to use this method, {@link enableProfiling} has to be set true.
     *
     * @return array the first element indicates the number of SQL statements executed,
     * and the second element the total time spent in SQL execution.
     */
    public function getStats()
    {
        return [];
    }

    public function getFromCache($key)
    {
        if ($this->cache) {
            return $this->cache->getFromCache($key);
        }

        return null;
    }

    public function addToCache($key, $value, $forever = false)
    {
        if ($this->cache) {
            $this->cache->addToCache($key, $value, $forever);
        }
    }

    public function getSchemaExtrasForTables($table_names, $include_fields = true, $select = '*')
    {
        if ($this->extraStore) {
            return $this->extraStore->getSchemaExtrasForTables($table_names, $include_fields, $select);
        }

        return null;
    }

    public function getSchemaExtrasForFields($table_name, $field_names, $select = '*')
    {
        if ($this->extraStore) {
            return $this->extraStore->getSchemaExtrasForFields($table_name, $field_names, $select);
        }

        return null;
    }

    public function setSchemaTableExtras($extras)
    {
        if ($this->extraStore) {
            $this->extraStore->setSchemaTableExtras($extras);
        }

        return null;
    }

    public function setSchemaFieldExtras($extras)
    {
        if ($this->extraStore) {
            $this->extraStore->setSchemaFieldExtras($extras);
        }

        return null;
    }

    public function removeSchemaExtrasForTables($table_names)
    {
        if ($this->extraStore) {
            $this->extraStore->removeSchemaExtrasForTables($table_names);
        }

        return null;
    }

    public function removeSchemaExtrasForFields($table_name, $field_names)
    {
        if ($this->extraStore) {
            $this->extraStore->removeSchemaExtrasForFields($table_name, $field_names);
        }

        return null;
    }

    public function updateSchema($tables, $allow_merge = false, $allow_delete = false, $rollback = false)
    {
        if (!is_array($tables) || empty($tables)) {
            throw new \Exception('There are no table sets in the request.');
        }

        if (!isset($tables[0])) {
            // single record possibly passed in without wrapper array
            $tables = [$tables];
        }

        $created = [];
        $references = [];
        $indexes = [];
        $out = [];
        $tableExtras = [];
        $fieldExtras = [];
        $count = 0;
        $singleTable = (1 == count($tables));

        foreach ($tables as $table) {
            try {
                if (empty($tableName = (isset($table['name'])) ? $table['name'] : null)) {
                    throw new \Exception('Table name missing from schema.');
                }

                //	Does it already exist
                if ($this->getSchema()->doesTableExist($tableName)) {
                    if (!$allow_merge) {
                        throw new \Exception("A table with name '$tableName' already exist in the database.");
                    }

                    \Log::debug('Schema update: ' . $tableName);

                    $results = $this->updateTable($tableName, $table, $allow_delete);
                } else {
                    \Log::debug('Creating table: ' . $tableName);

                    $results = $this->createTable($tableName, $table);

                    if (!$singleTable && $rollback) {
                        $created[] = $tableName;
                    }
                }

                // add table extras
                $extras = array_only($table, ['label', 'plural', 'alias', 'description', 'name_field']);
                if (!empty($extras)) {
                    $extras['table'] = $tableName;
                    $tableExtras[] = $extras;
                }

                $fieldExtras = array_merge($fieldExtras, (isset($results['labels'])) ? $results['labels'] : []);
                $references = array_merge($references, (isset($results['references'])) ? $results['references'] : []);
                $indexes = array_merge($indexes, (isset($results['indexes'])) ? $results['indexes'] : []);
                $out[$count] = ['name' => $tableName];
            } catch (\Exception $ex) {
                if ($rollback || $singleTable) {
                    //  Delete any created tables
                    throw $ex;
                }

                $out[$count] = [
                    'error' => [
                        'message' => $ex->getMessage(),
                        'code'    => $ex->getCode()
                    ]
                ];
            }

            $count++;
        }

        if (!empty($references)) {
            $this->createFieldReferences($references);
        }
        if (!empty($indexes)) {
            $this->createFieldIndexes($indexes);
        }
        if (!empty($tableExtras)) {
            $this->setSchemaTableExtras($tableExtras);
        }
        if (!empty($fieldExtras)) {
            $this->setSchemaFieldExtras($fieldExtras);
        }

        return $out;
    }

    /**
     * Builds and executes a SQL statement for creating a new DB table.
     *
     * The columns in the new table should be specified as name-definition pairs (e.g. 'name'=>'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which can contain an abstract DB type.
     * The {@link getColumnType} method will be invoked to convert any abstract type into a physical one.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * inserted into the generated SQL.
     *
     * @param string $table   the name of the table to be created. The name will be properly quoted by the method.
     * @param array  $schema  the table schema for the new table.
     * @param string $options additional SQL fragment that will be appended to the generated SQL.
     *
     * @return int 0 is always returned. See <a
     *             href='http://php.net/manual/en/pdostatement.rowcount.php'>http://php.net/manual/en/pdostatement.rowcount.php</a>
     *             for more for more information.
     * @throws \Exception
     * @since 1.1.6
     */
    public function createTable($table, $schema, $options = null)
    {
        if (empty($schema['field'])) {
            throw new \Exception("No valid fields exist in the received table schema.");
        }

        $results = $this->getSchema()->buildTableFields($table, $schema['field']);
        if (empty($results['columns'])) {
            throw new \Exception("No valid fields exist in the received table schema.");
        }

        $command = $this->createCommand();
        $command->createTable($table, $results['columns'], $options);

        if (!empty($results['extras'])) {
            foreach ($results['extras'] as $extraCommand) {
                try {
                    $command->reset();
                    $command->setText($extraCommand)->execute();
                } catch (\Exception $ex) {
                    // oh well, we tried.
                }
            }
        }

        return $results;
    }

    /**
     * @param string $table_name
     * @param array  $schema
     * @param bool   $allow_delete
     *
     * @throws \Exception
     * @return array
     */
    protected function updateTable($table_name, $schema, $allow_delete = false)
    {
        if (empty($table_name)) {
            throw new \Exception("Table schema received does not have a valid name.");
        }

        // does it already exist
        if (!$this->getSchema()->doesTableExist($table_name)) {
            throw new \Exception("Update schema called on a table with name '$table_name' that does not exist in the database.");
        }

        //  Is there a name update
        if (!empty($schema['new_name'])) {
            // todo change table name, has issue with references
        }

        $oldSchema = $this->getSchema()->getTable($table_name);

        // update column types

        $results = [];
        $command = $this->createCommand();
        if (!empty($schema['field'])) {
            $results =
                $this->getSchema()->buildTableFields($table_name, $schema['field'], $oldSchema, true, $allow_delete);
            if (isset($results['columns']) && is_array($results['columns'])) {
                foreach ($results['columns'] as $name => $definition) {
                    $command->reset();
                    $command->addColumn($table_name, $name, $definition);
                }
            }
            if (isset($results['alter_columns']) && is_array($results['alter_columns'])) {
                foreach ($results['alter_columns'] as $name => $definition) {
                    $command->reset();
                    $command->alterColumn($table_name, $name, $definition);
                }
            }
            if (isset($results['drop_columns']) && is_array($results['drop_columns'])) {
                foreach ($results['drop_columns'] as $name) {
                    $command->reset();
                    $command->dropColumn($table_name, $name);
                }
            }
        }

        return $results;
    }

    /**
     * Builds and executes a SQL statement for dropping a DB table.
     *
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     *
     * @return integer 0 is always returned. See {@link http://php.net/manual/en/pdostatement.rowcount.php} for more
     *                 information.
     * @since 1.1.6
     */
    public function dropTable($table)
    {
        $result = $this->createCommand()->dropTable($table);
        $this->removeSchemaExtrasForTables($table);

        //  Any changes here should refresh cached schema
        $this->getSchema()->refresh();

        return $result;
    }

    public function dropColumn($table, $column)
    {
        $result = $this->createCommand()->dropColumn($table, $column);
        $this->removeSchemaExtrasForFields($table, $column);

        //  Any changes here should refresh cached schema
        $this->getSchema()->refresh();

        return $result;
    }

    /**
     * @param string $table_name
     * @param array  $fields
     * @param bool   $allow_update
     * @param bool   $allow_delete
     *
     * @return array
     * @throws \Exception
     */
    public function updateFields($table_name, $fields, $allow_update = false, $allow_delete = false)
    {
        if (empty($table_name)) {
            throw new \Exception("Table schema received does not have a valid name.");
        }

        // does it already exist
        if (!$this->getSchema()->doesTableExist($table_name)) {
            throw new \Exception("Update schema called on a table with name '$table_name' that does not exist in the database.");
        }

        $oldSchema = $this->getSchema()->getTable($table_name);

        $command = $this->createCommand();
        $names = [];
        $results = $this->getSchema()->buildTableFields($table_name, $fields, $oldSchema, $allow_update, $allow_delete);
        if (isset($results['columns']) && is_array($results['columns'])) {
            foreach ($results['columns'] as $name => $definition) {
                $command->reset();
                $command->addColumn($table_name, $name, $definition);
                $names[] = $name;
            }
        }
        if (isset($results['alter_columns']) && is_array($results['alter_columns'])) {
            foreach ($results['alter_columns'] as $name => $definition) {
                $command->reset();
                $command->alterColumn($table_name, $name, $definition);
                $names[] = $name;
            }
        }
        if (isset($results['drop_columns']) && is_array($results['drop_columns'])) {
            foreach ($results['drop_columns'] as $name) {
                $command->reset();
                $command->dropColumn($table_name, $name);
                $names[] = $name;
            }
        }

        $references = (isset($results['references'])) ? $results['references'] : [];
        $this->createFieldReferences($references);

        $indexes = (isset($results['indexes'])) ? $results['indexes'] : [];
        $this->createFieldIndexes($indexes);

        $labels = (isset($results['labels'])) ? $results['labels'] : [];
        if (!empty($labels)) {
            $this->setSchemaFieldExtras($labels);
        }

        return ['names' => $names];
    }

    /**
     * @param array $references
     *
     * @return array
     */
    protected function createFieldReferences($references)
    {
        if (!empty($references)) {
            $command = $this->createCommand();
            foreach ($references as $reference) {
                $name = $reference['name'];
                $table = $reference['table'];
                $drop = (isset($reference['drop'])) ? boolval($reference['drop']) : false;
                if ($drop) {
                    try {
                        $command->reset();
                        $command->dropForeignKey($name, $table);
                    } catch (\Exception $ex) {
                        \Log::debug($ex->getMessage());
                    }
                }
                // add new reference
                $refTable = (isset($reference['ref_table'])) ? $reference['ref_table'] : null;
                if (!empty($refTable)) {
                    $command->reset();
                    /** @noinspection PhpUnusedLocalVariableInspection */
                    $rows = $command->addForeignKey(
                        $name,
                        $table,
                        $reference['column'],
                        $refTable,
                        $reference['ref_fields'],
                        $reference['delete'],
                        $reference['update']
                    );
                }
            }
        }
    }

    /**
     * @param array $indexes
     *
     * @return array
     */
    protected function createFieldIndexes($indexes)
    {
        if (!empty($indexes)) {
            $command = $this->createCommand();
            foreach ($indexes as $index) {
                $name = $index['name'];
                $table = $index['table'];
                $drop = (isset($index['drop'])) ? boolval($index['drop']) : false;
                if ($drop) {
                    try {
                        $command->reset();
                        $command->dropIndex($name, $table);
                    } catch (\Exception $ex) {
                        \Log::debug($ex->getMessage());
                    }
                }
                $unique = (isset($index['unique'])) ? boolval($index['unique']) : false;

                $command->reset();
                /** @noinspection PhpUnusedLocalVariableInspection */
                $rows = $command->createIndex($name, $table, $index['column'], $unique);
            }
        }
    }
}
