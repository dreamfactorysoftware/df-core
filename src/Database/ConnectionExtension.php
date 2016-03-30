<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\CacheInterface;

/**
 * ConnectionExtension represents a connection to a database with DreamFactory extensions.
 *
 */
trait ConnectionExtension
{
    /**
     * @var CacheInterface
     */
    protected $cache = null;
    /**
     * @var DbExtrasInterface
     */
    protected $extraStore = null;
    /**
     * @var boolean
     */
    protected $defaultSchemaOnly = false;
    /**
     * @var array
     */
    protected $attributes = [];
    /**
     * @var Schema
     */
    protected $schemaExtension;

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

    public function getUserName()
    {
        return array_get($this->config, 'username');
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
        return new Command($this, $query);
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
        return $this->getPdo()->lastInsertId($sequenceName);
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

    public function getFromCache($key, $default = null)
    {
        if ($this->cache) {
            return $this->cache->getFromCache($key, $default);
        }

        return null;
    }

    public function addToCache($key, $value, $forever = false)
    {
        if ($this->cache) {
            $this->cache->addToCache($key, $value, $forever);
        }
    }

    public function removeFromCache($key)
    {
        if ($this->cache) {
            $this->cache->removeFromCache($key);
        }
    }

    public function flush()
    {
        if ($this->cache) {
            $this->cache->flush();
        }
    }

    public function getSchemaExtrasForTables($table_names, $include_fields = true, $select = '*')
    {
        if ($this->extraStore) {
            return $this->extraStore->getSchemaExtrasForTables($table_names, $include_fields, $select);
        }

        return null;
    }

    public function getSchemaExtrasForFields($table_name, $field_names = '*', $select = '*')
    {
        if ($this->extraStore) {
            return $this->extraStore->getSchemaExtrasForFields($table_name, $field_names, $select);
        }

        return null;
    }

    public function getSchemaExtrasForFieldsReferenced($table_name, $field_names = '*', $select = '*')
    {
        if ($this->extraStore) {
            return $this->extraStore->getSchemaExtrasForFieldsReferenced($table_name, $field_names, $select);
        }

        return null;
    }

    public function getSchemaExtrasForRelated($table_name, $related_names = '*', $select = '*')
    {
        if ($this->extraStore) {
            return $this->extraStore->getSchemaExtrasForRelated($table_name, $related_names, $select);
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

    public function setSchemaRelatedExtras($extras)
    {
        if ($this->extraStore) {
            $this->extraStore->setSchemaRelatedExtras($extras);
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

    public function removeSchemaExtrasForRelated($table_name, $related_names)
    {
        if ($this->extraStore) {
            $this->extraStore->removeSchemaExtrasForRelated($table_name, $related_names);
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
        $fieldDrops = [];
        $relatedExtras = [];
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

                // add relationship extras
                if (!empty($relationships = (isset($table['related'])) ? $table['related'] : null)) {
                    if (is_array($relationships)) {
                        foreach ($relationships as $info) {
                            if (isset($info, $info['name'])) {
                                $relationship = $info['name'];
                                $toSave =
                                    array_only($info,
                                        [
                                            'label',
                                            'description',
                                            'alias',
                                            'always_fetch',
                                            'flatten',
                                            'flatten_drop_prefix'
                                        ]);
                                if (!empty($toSave)) {
                                    $toSave['relationship'] = $relationship;
                                    $toSave['table'] = $tableName;
                                    $relatedExtras[] = $toSave;
                                }
                            }
                        }
                    }
                }

                $fieldExtras = array_merge($fieldExtras, (isset($results['extras'])) ? $results['extras'] : []);
                $fieldDrops = array_merge($fieldDrops, (isset($results['drop_extras'])) ? $results['drop_extras'] : []);
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
        if (!empty($fieldDrops)) {
            foreach ($fieldDrops as $table => $dropFields) {
                $this->removeSchemaExtrasForFields($table, $dropFields);
            }
        }
        if (!empty($relatedExtras)) {
            $this->setSchemaRelatedExtras($relatedExtras);
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

        if (!empty($results['commands'])) {
            foreach ($results['commands'] as $extraCommand) {
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
        $result = 0;
        $tableInfo = $this->getSchema()->getTable($table);
        if (($columnInfo = $tableInfo->getColumn($column)) && (ColumnSchema::TYPE_VIRTUAL !== $columnInfo->type)) {
            $result = $this->createCommand()->dropColumn($table, $column);
        }
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

        $extras = (isset($results['extras'])) ? $results['extras'] : [];
        if (!empty($extras)) {
            $this->setSchemaFieldExtras($extras);
        }

        $extras = (isset($results['drop_extras'])) ? $results['drop_extras'] : [];
        if (!empty($extras)) {
            foreach ($extras as $table => $dropFields) {
                $this->removeSchemaExtrasForFields($table, $dropFields);
            }
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
