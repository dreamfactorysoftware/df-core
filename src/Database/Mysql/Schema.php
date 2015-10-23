<?php
namespace DreamFactory\Core\Database\Mysql;

use DreamFactory\Core\Database\TableNameSchema;
use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a MySQL database (version 4.1.x and 5.x).
 */
class Schema extends \DreamFactory\Core\Database\Schema
{
    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'pk':
            case 'id':
                $info['type'] = 'int';
                $info['type_extras'] = '(11)';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case 'reference':
                $info['type'] = 'int';
                $info['type_extras'] = '(11)';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case 'timestamp_on_create':
            case 'timestamp_on_update':
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $default = 'CURRENT_TIMESTAMP';
                    if ('timestamp_on_update' === $type) {
                        $default .= ' ON UPDATE CURRENT_TIMESTAMP';
                    }
                    $info['default'] = ['expression' => $default];
                }
                break;

            case 'user_id':
            case 'user_id_on_create':
            case 'user_id_on_update':
                $info['type'] = 'int';
                $info['type_extras'] = '(11)';
                break;

            case 'boolean':
                $info['type'] = 'tinyint';
                $info['type_extras'] = '(1)';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case 'money':
                $info['type'] = 'decimal';
                $info['type_extras'] = '(19,4)';
                break;

            case 'string':
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $national =
                    (isset($info['supports_multibyte'])) ? filter_var($info['supports_multibyte'],
                        FILTER_VALIDATE_BOOLEAN) : false;
                if ($fixed) {
                    $info['type'] = ($national) ? 'nchar' : 'char';
                } elseif ($national) {
                    $info['type'] = 'nvarchar';
                } else {
                    $info['type'] = 'varchar';
                }
                break;

            case 'binary':
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $info['type'] = ($fixed) ? 'binary' : 'varbinary';
                break;
        }
    }

    protected function validateColumnSettings(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'bit':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'bigint':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $info['type_extras'] = "($length)"; // sets the viewable length
                    }
                }

                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = intval($default);
                }
                break;

            case 'decimal':
            case 'numeric':
            case 'real':
            case 'float':
            case 'double':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $scale =
                            (isset($info['decimals']))
                                ? $info['decimals']
                                : ((isset($info['scale'])) ? $info['scale']
                                : null);
                        $info['type_extras'] = (!empty($scale)) ? "($length,$scale)" : "($length)";
                    }
                }

                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default) && is_numeric($default)) {
                    $info['default'] = floatval($default);
                }
                break;

            case 'char':
            case 'nchar':
            case 'binary':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;

            case 'varchar':
            case 'nvarchar':
            case 'varbinary':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                } else // requires a max length
                {
                    $info['type_extras'] = '(' . static::DEFAULT_STRING_MAX_SIZE . ')';
                }
                break;

            case 'time':
            case 'timestamp':
            case 'datetime':
                $default = (isset($info['default'])) ? $info['default'] : null;
                if ('0000-00-00 00:00:00' == $default) {
                    // read back from MySQL has formatted zeros, can't send that back
                    $info['default'] = 0;
                }

                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;
        }
    }

    /**
     * @param array $info
     *
     * @return string
     * @throws \Exception
     */
    protected function buildColumnDefinition(array $info)
    {
        $type = (isset($info['type'])) ? $info['type'] : null;
        $typeExtras = (isset($info['type_extras'])) ? $info['type_extras'] : null;

        $definition = $type . $typeExtras;

        $allowNull = (isset($info['allow_null'])) ? filter_var($info['allow_null'], FILTER_VALIDATE_BOOLEAN) : false;
        $definition .= ($allowNull) ? ' NULL' : ' NOT NULL';

        $default = (isset($info['default'])) ? $info['default'] : null;
        if (isset($default)) {
            if (is_array($default)) {
                $expression = (isset($default['expression'])) ? $default['expression'] : null;
                if (null !== $expression) {
                    $definition .= ' DEFAULT ' . $expression;
                }
            } else {
                $default = $this->connection->quoteValue($default);
                $definition .= ' DEFAULT ' . $default;
            }
        }

        $auto = (isset($info['auto_increment'])) ? filter_var($info['auto_increment'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($auto) {
            $definition .= ' AUTO_INCREMENT';
        }

        $isUniqueKey = (isset($info['is_unique'])) ? filter_var($info['is_unique'], FILTER_VALIDATE_BOOLEAN) : false;
        $isPrimaryKey =
            (isset($info['is_primary_key'])) ? filter_var($info['is_primary_key'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($isPrimaryKey && $isUniqueKey) {
            throw new \Exception('Unique and Primary designations not allowed simultaneously.');
        }

        if ($isUniqueKey) {
            $definition .= ' UNIQUE KEY';
        } elseif ($isPrimaryKey) {
            $definition .= ' PRIMARY KEY';
        }

        return $definition;
    }

    /**
     * Quotes a table name for use in a query.
     * A simple table name does not schema prefix.
     *
     * @param string $name table name
     *
     * @return string the properly quoted table name
     * @since 1.1.6
     */
    public function quoteSimpleTableName($name)
    {
        return '`' . $name . '`';
    }

    /**
     * Quotes a column name for use in a query.
     * A simple column name does not contain prefix.
     *
     * @param string $name column name
     *
     * @return string the properly quoted column name
     * @since 1.1.6
     */
    public function quoteSimpleColumnName($name)
    {
        return '`' . $name . '`';
    }

    /**
     * Compares two table names.
     * The table names can be either quoted or unquoted. This method
     * will consider both cases.
     *
     * @param string $name1 table name 1
     * @param string $name2 table name 2
     *
     * @return boolean whether the two table names refer to the same table.
     */
    public function compareTableNames($name1, $name2)
    {
        return parent::compareTableNames(strtolower($name1), strtolower($name2));
    }

    /**
     * Resets the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or max value of a primary key plus one (i.e. sequence trimming).
     *
     * @param TableSchema  $table   the table schema whose primary key sequence will be reset
     * @param integer|null $value   the value for the primary key of the next new row inserted.
     *                              If this is not set, the next new row's primary key will have the max value of a
     *                              primary key plus one (i.e. sequence trimming).
     *
     * @return int|void
     * @since 1.1
     */
    public function resetSequence($table, $value = null)
    {
        if ($table->sequenceName === null) {
            return;
        }

        if ($value !== null) {
            $value = (int)$value;
        } else {
            $value =
                (int)$this->connection->createCommand('SELECT MAX(`' .
                    $table->primaryKey .
                    '`) + 1 FROM ' .
                    $table->rawName)->queryScalar();
        }

        $this->connection->createCommand(
            <<<MYSQL
ALTER TABLE {$table->rawName} AUTO_INCREMENT = :value
MYSQL
        )->execute([':value' => $value]);
    }

    /**
     * Enables or disables integrity check.
     *
     * @param boolean $check  whether to turn on or off the integrity check.
     * @param string  $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
     * @since 1.1
     */
    public function checkIntegrity($check = true, $schema = '')
    {
        $this->connection->createCommand('SET FOREIGN_KEY_CHECKS=' . ($check ? 1 : 0))->execute();
    }

    /**
     * Loads the metadata for the specified table.
     *
     * @param string $name table name
     *
     * @return TableSchema driver dependent table metadata. Null if the table does not exist.
     */
    protected function loadTable($name)
    {
        $table = new TableSchema($name);
        $this->resolveTableNames($table, $name);

        if (!$this->findColumns($table)) {
            return null;
        }

        $this->findConstraints($table);

        return $table;
    }

    /**
     * Generates various kinds of table names.
     *
     * @param TableSchema $table the table instance
     * @param string      $name  the unquoted table name
     */
    protected function resolveTableNames($table, $name)
    {
        $parts = explode('.', str_replace(['`', '"'], '', $name));
        if (isset($parts[1])) {
            $table->schemaName = $parts[0];
            $table->name = $parts[1];
            $table->rawName = $this->quoteTableName($table->schemaName) . '.' . $this->quoteTableName($table->name);
            $table->displayName =
                ($table->schemaName === $this->getDefaultSchema())
                    ? $table->name
                    : ($table->schemaName .
                    '.' .
                    $table->name);
        } else {
            $table->name = $parts[0];
            $table->rawName = $this->quoteTableName($table->name);
            $table->displayName = $table->name;
        }
    }

    /**
     * Collects the table column metadata.
     *
     * @param TableSchema $table the table metadata
     *
     * @return boolean whether the table exists in the database
     */
    protected function findColumns($table)
    {
        $sql = 'SHOW FULL COLUMNS FROM ' . $table->rawName;
        try {
            $columns = $this->connection->createCommand($sql)->queryAll();
        } catch (\Exception $e) {
            return false;
        }
        foreach ($columns as $column) {
            $c = $this->createColumn($column);
            if ($c->isPrimaryKey) {
                if ($table->primaryKey === null) {
                    $table->primaryKey = $c->name;
                } elseif (is_string($table->primaryKey)) {
                    $table->primaryKey = [$table->primaryKey, $c->name];
                } else {
                    $table->primaryKey[] = $c->name;
                }
                if ($c->autoIncrement) {
                    $table->sequenceName = '';
                    if (('integer' === $c->type)) {
                        $c->type = 'id';
                    }
                }
            }
            $table->addColumn($c);
        }

        return true;
    }

    /**
     * Creates a table column.
     *
     * @param array $column column metadata
     *
     * @return ColumnSchema normalized column metadata
     */
    protected function createColumn($column)
    {
        $c = new ColumnSchema(['name' => $column['Field']]);
        $c->rawName = $this->quoteColumnName($c->name);
        $c->allowNull = $column['Null'] === 'YES';
        $c->isPrimaryKey = strpos($column['Key'], 'PRI') !== false;
        $c->isUnique = strpos($column['Key'], 'UNI') !== false;
        $c->isIndex = strpos($column['Key'], 'MUL') !== false;
        $c->autoIncrement = strpos(strtolower($column['Extra']), 'auto_increment') !== false;
        $c->dbType = $column['Type'];
        if (isset($column['Collation']) && !empty($column['Collation'])) {
            $collation = $column['Collation'];
            if (0 === stripos($collation, 'utf') || 0 === stripos($collation, 'ucs')) {
                $c->supportsMultibyte = true;
            }
        }
        if (isset($column['Comment'])) {
            $c->comment = $column['Comment'];
        }
        $c->extractLimit($column['Type']);
        $c->extractFixedLength($column['Type']);
//        $c->extractMultiByteSupport( $column['Type'] );
        $c->extractType($column['Type']);

        if ($c->dbType === 'timestamp' && (0 === strcasecmp(strval($column['Default']), 'CURRENT_TIMESTAMP'))) {
            if (0 === strcasecmp(strval($column['Extra']), 'on update CURRENT_TIMESTAMP')) {
                $c->defaultValue = ['expression' => 'CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP'];
                $c->type = 'timestamp_on_update';
            } else {
                $c->defaultValue = ['expression' => 'CURRENT_TIMESTAMP'];
                $c->type = 'timestamp_on_create';
            }
        } else {
            $c->extractDefault($column['Default']);
        }

        return $c;
    }

    /**
     * @return float server version.
     */
    protected function getServerVersion()
    {
        $version = $this->connection->getAttribute(\PDO::ATTR_SERVER_VERSION);
        $digits = [];
        preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $digits);

        return floatval($digits[1] . '.' . $digits[2] . $digits[3]);
    }

    /**
     * Collects the foreign key column details for the given table.
     * Also, collects the foreign tables and columns that reference the given table.
     *
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        $defaultSchema = $this->getDefaultSchema();
        $tableSchema = (!empty($table->schemaName)) ? $table->schemaName : $this->getDefaultSchema();
        $columns = [];
        foreach ($this->getSchemaNames() as $schema) {
            $sql = <<<MYSQL
SELECT table_schema, table_name, column_name, referenced_table_schema, referenced_table_name, referenced_column_name
FROM information_schema.KEY_COLUMN_USAGE WHERE referenced_table_name IS NOT NULL AND table_schema = '$schema';
MYSQL;

            $columns = array_merge($columns, $this->connection->createCommand($sql)->queryAll());
        }

        $columns2 = $columns;
        foreach ($columns as $key => $column) {
            $ts = $column['table_schema'];
            $tn = $column['table_name'];
            $cn = $column['column_name'];
            $rts = $column['referenced_table_schema'];
            $rtn = $column['referenced_table_name'];
            $rcn = $column['referenced_column_name'];
            if ((0 == strcasecmp($tn, $table->name)) && (0 == strcasecmp($ts, $tableSchema))) {
                $name = ($rts == $defaultSchema) ? $rtn : $rts . '.' . $rtn;

                $table->foreignKeys[$cn] = [$name, $rcn];
                $cnk = strtolower($cn);
                if (isset($table->columns[$cnk])) {
                    $table->columns[$cnk]->isForeignKey = true;
                    $table->columns[$cnk]->refTable = $name;
                    $table->columns[$cnk]->refFields = $rcn;
                    if ('integer' === $table->columns[$cnk]->type) {
                        $table->columns[$cnk]->type = 'reference';
                    }
                }

                // Add it to our foreign references as well
                $table->addRelation('belongs_to', $name, $rcn, $cn);
            } elseif ((0 == strcasecmp($rtn, $table->name)) && (0 == strcasecmp($rts, $tableSchema))) {
                $name = ($ts == $defaultSchema) ? $tn : $ts . '.' . $tn;
                $table->addRelation('has_many', $name, $cn, $rcn);

                // if other has foreign keys to other tables, we can say these are related as well
                foreach ($columns2 as $key2 => $column2) {
                    if (0 != strcasecmp($key, $key2)) // not same key
                    {
                        $ts2 = $column2['table_schema'];
                        $tn2 = $column2['table_name'];
                        $cn2 = $column2['column_name'];
                        if ((0 == strcasecmp($ts2, $ts)) && (0 == strcasecmp($tn2, $tn))
                        ) {
                            $rts2 = $column2['referenced_table_schema'];
                            $rtn2 = $column2['referenced_table_name'];
                            $rcn2 = $column2['referenced_column_name'];
                            if ((0 != strcasecmp($rts2, $tableSchema)) || (0 != strcasecmp($rtn2, $table->name))
                            ) {
                                $name2 = ($rts2 == $defaultSchema) ? $rtn2 : $rts2 . '.' . $rtn2;
                                // not same as parent, i.e. via reference back to self
                                // not the same key
                                $table->addRelation('many_many', $name2, $rcn2, $rcn, "$name($cn,$cn2)");
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns all non-system database/schema names on the server
     *
     * @return array|void
     */
    protected function findSchemaNames()
    {
        $sql = <<<MYSQL
SHOW DATABASES WHERE `Database` NOT IN ('information_schema','mysql','performance_schema','phpmyadmin')
MYSQL;

        return $this->connection->createCommand($sql)->queryColumn();
    }

    /**
     * Returns all table names in the database.
     *
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *                       If not empty, the returned table names will be prefixed with the schema name.
     * @param bool   $include_views
     *
     * @return array all table names in the database.
     */
    protected function findTableNames($schema = '', $include_views = true)
    {
        $defaultSchema = $this->getDefaultSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $sql = 'SHOW FULL TABLES';

        if (!empty($schema)) {
            $sql .= ' FROM ' . $this->quoteTableName($schema);
        }

        if (!$include_views) {
            $sql .= " WHERE TABLE_TYPE = 'BASE TABLE'";
        }

        $rows = $this->connection->createCommand($sql)->queryAll();

        $names = [];
        foreach ($rows as $row) {
            $row = array_values($row);
            $name = $row[0];
            if ($addSchema) {
                $name = $schema . '.' . $name;
            }
            $names[strtolower($name)] = new TableNameSchema($name, (0 === strcasecmp('VIEW', $row[1])));
        }

        return $names;
    }

    /**
     * Returns all stored procedure names in the database.
     *
     * @param string $schema the schema of the stored procedures. Defaults to empty string, meaning the current or
     *                       default schema. If not empty, the returned stored procedure names will be prefixed with
     *                       the schema name.
     *
     * @return array all stored procedure names in the database.
     */
    protected function findProcedureNames($schema = '')
    {
        return $this->findRoutines('procedure', $schema);
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
        $name = $this->connection->quoteTableName($name);
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

        !empty($pre) && $this->connection->createCommand($pre)->execute();

        $sql = "CALL $name($paramStr);";
        $command = $this->connection->createCommand($sql);

        // do binding
        $command->bindValues($bindings);

        // Move to the next result and get results
        $reader = $command->query();
        $result = $reader->readAll();
        if ($reader->nextResult()) {
            // more data coming, make room
            $result = [$result];
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
        }

        if (!empty($post)) {
            $out = $this->connection->createCommand($post . ';')->queryRow();
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
     * Returns all stored function names in the database.
     *
     * @param string $schema the schema of the stored function. Defaults to empty string, meaning the current or
     *                       default schema. If not empty, the returned stored function names will be prefixed with the
     *                       schema name.
     *
     * @return array all stored function names in the database.
     */
    protected function findFunctionNames($schema = '')
    {
        return $this->findRoutines('function', $schema);
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
        $name = $this->connection->quoteTableName($name);
        $bindings = [];
        foreach ($params as $key => $param) {
            $pName = (isset($param['name']) && !empty($param['name'])) ? ':' . $param['name'] : ":p$key";
            $pValue = isset($param['value']) ? $param['value'] : null;

            $bindings[$pName] = $pValue;
        }

        $paramStr = implode(',', array_keys($bindings));
        $sql = "SELECT $name($paramStr);";
        $command = $this->connection->createCommand($sql);

        // do binding
        $command->bindValues($bindings);

        // Move to the next result and get results
        $reader = $command->query();
        $result = $reader->readAll();
        if ($reader->nextResult()) {
            // more data coming, make room
            $result = [$result];
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
        }

        return $result;
    }

    /**
     * Creates a command builder for the database.
     * This method overrides parent implementation in order to create a MySQL specific command builder
     *
     * @return CommandBuilder command builder instance
     * @since 1.1.13
     */
    protected function createCommandBuilder()
    {
        return new CommandBuilder($this);
    }

    /**
     * Builds a SQL statement for renaming a column.
     *
     * @param string $table   the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name    the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     *
     * @throws \Exception if specified column is not found in given table
     * @return string the SQL statement for renaming a DB column.
     * @since 1.1.6
     */
    public function renameColumn($table, $name, $newName)
    {
        $db = $this->connection;
        $row = $db->createCommand('SHOW CREATE TABLE ' . $db->quoteTableName($table))->queryRow();

        if ($row === false) {
            throw new \Exception("Unable to find '$name' in table '$table'.");
        }

        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }

        $table = $db->quoteTableName($table);
        $name = $db->quoteColumnName($name);
        $newName = $db->quoteColumnName($newName);

        if (preg_match_all('/^\s*[`"](.*?)[`"]\s+(.*?),?$/m', $sql, $matches)) {
            foreach ($matches[1] as $i => $c) {
                if ($c === $name) {
                    return <<<MYSQL
ALTER TABLE {$table} CHANGE {$name} $newName {$matches[2][$i]}
MYSQL;
                }
            }
        }

        return <<<MYSQL
ALTER TABLE {$table} CHANGE {$name} $newName
MYSQL;
    }

    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     *
     * @param string $name  the name of the foreign key constraint to be dropped. The name will be properly quoted by
     *                      the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for dropping a foreign key constraint.
     * @since 1.1.6
     */
    public function dropForeignKey($name, $table)
    {
        return 'ALTER TABLE ' . $this->quoteTableName($table) . ' DROP FOREIGN KEY ' . $this->quoteColumnName($name);
    }

    /**
     * Builds a SQL statement for removing a primary key constraint to an existing table.
     *
     * @param string $name  the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     *
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     * @since 1.1.13
     */
    public function dropPrimaryKey($name, $table)
    {
        return 'ALTER TABLE ' . $this->quoteTableName($table) . ' DROP PRIMARY KEY';
    }

    /**
     * Builds a SQL statement for adding a primary key constraint to a table.
     *
     * @param string       $name    not used in the MySQL syntax, the primary key is always called PRIMARY and is
     *                              reserved.
     * @param string       $table   the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     *
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     * @since 1.1.14
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        }

        foreach ($columns as $i => $col) {
            $columns[$i] = $this->quoteColumnName($col);
        }

        return 'ALTER TABLE ' . $this->quoteTableName($table) . ' ADD PRIMARY KEY (' . implode(', ', $columns) . ' )';
    }

    /**
     * Returns all routines in the database.
     *
     * @param string $type   "procedure" or "function"
     * @param string $schema the schema of the routine. Defaults to empty string, meaning the current or
     *                       default schema. If not empty, the returned stored function names will be prefixed with the
     *                       schema name.
     *
     * @throws \InvalidArgumentException
     * @return array all stored function names in the database.
     */
    protected function findRoutines($type, $schema = '')
    {
        $defaultSchema = $this->getDefaultSchema();
        $type = trim(strtoupper($type));

        if ($type != 'PROCEDURE' && $type != 'FUNCTION') {
            throw new \InvalidArgumentException('The type "' . $type . '" is invalid.');
        }

        $select =
            (empty($schema) || ($defaultSchema == $schema))
                ? 'ROUTINE_NAME' : "CONCAT('" . $schema . "','.',ROUTINE_NAME) as ROUTINE_NAME";
        $schema = !empty($schema) ? " AND ROUTINE_SCHEMA = '" . $schema . "'" : null;

        $sql = <<<MYSQL
SELECT
    {$select}
FROM
    information_schema.ROUTINES
WHERE
    ROUTINE_TYPE = :routine_type
    {$schema}
MYSQL;

        return $this->connection->createCommand($sql)->queryColumn([':routine_type' => $type]);
    }

    /**
     * @return string default schema.
     */
    public function findDefaultSchema()
    {
        $sql = <<<MYSQL
SELECT DATABASE() FROM DUAL
MYSQL;

        return $this->connection->createCommand($sql)->queryScalar();
    }

    public function parseValueForSet($value, $field_info)
    {
        switch ($field_info->type) {
            case 'boolean':
                $value = (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
                break;
        }

        return $value;
    }
}
