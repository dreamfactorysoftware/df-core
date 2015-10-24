<?php
namespace DreamFactory\Core\Database\Sqlanywhere;

use DreamFactory\Core\Database\Expression;
use DreamFactory\Core\Database\TableNameSchema;
use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a MS SQL Server database.
 */
class Schema extends \DreamFactory\Core\Database\Schema
{
    /**
     * @param boolean $refresh if we need to refresh schema cache.
     *
     * @return string default schema.
     */
    public function getDefaultSchema($refresh = false)
    {
        return strtoupper($this->connection->username);
    }

    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'pk':
            case 'id':
                $info['type'] = 'int';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case 'reference':
                $info['type'] = 'int';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case 'timestamp_on_create':
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $info['default'] = ['expression' => 'CURRENT TIMESTAMP'];
                }
                break;
            case 'timestamp_on_update':
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $info['default'] = ['expression' => 'TIMESTAMP'];
                }
                break;
            case 'user_id':
            case 'user_id_on_create':
            case 'user_id_on_update':
                $info['type'] = 'int';
                break;

            case 'boolean':
                $info['type'] = 'bit';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case 'integer':
                $info['type'] = 'int';
                break;

            case 'double':
                $info['type'] = 'float';
                $info['type_extras'] = '(53)';
                break;

            case 'text':
                $info['type'] = 'long varchar';
                break;
            case 'ntext':
                $info['type'] = 'long nvarchar';
                break;
            case 'image':
                $info['type'] = 'varbinary';
                $info['type_extras'] = '(max)';
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
            case 'money':
            case 'smallmoney':
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
            case 'real':
            case 'float':
                if (!isset($info['type_extras'])) {
                    $length =
                        (isset($info['length']))
                            ? $info['length']
                            : ((isset($info['precision'])) ? $info['precision']
                            : null);
                    if (!empty($length)) {
                        $info['type_extras'] = "($length)";
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
            case 'datetime':
            case 'timestamp':
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
            $definition .= ' IDENTITY';
        }

        $isUniqueKey = (isset($info['is_unique'])) ? filter_var($info['is_unique'], FILTER_VALIDATE_BOOLEAN) : false;
        $isPrimaryKey =
            (isset($info['is_primary_key'])) ? filter_var($info['is_primary_key'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($isPrimaryKey && $isUniqueKey) {
            throw new \Exception('Unique and Primary designations not allowed simultaneously.');
        }

        if ($isUniqueKey) {
            $definition .= ' UNIQUE';
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
        return '[' . $name . ']';
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
        return '[' . $name . ']';
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
        $name1 = str_replace(['[', ']'], '', $name1);
        $name2 = str_replace(['[', ']'], '', $name2);

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
     * @since 1.1.6
     */
    public function resetSequence($table, $value = null)
    {
        if ($table->sequenceName === null) {
            return;
        }
        if ($value !== null) {
            $value = (int)($value) - 1;
        } else {
            $value =
                (int)$this->connection
                    ->createCommand("SELECT MAX([{$table->primaryKey}]) FROM {$table->rawName}")
                    ->queryScalar();
        }
        $name = strtr($table->rawName, ['[' => '', ']' => '']);
        $this->connection->createCommand("DBCC CHECKIDENT ('$name',RESEED,$value)")->execute();
    }

    private $normalTables = [];  // non-view tables

    /**
     * Enables or disables integrity check.
     *
     * @param boolean $check  whether to turn on or off the integrity check.
     * @param string  $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
     * @since 1.1.6
     */
    public function checkIntegrity($check = true, $schema = '')
    {
        $enable = $check ? 'CHECK' : 'NOCHECK';
        if (!isset($this->normalTables[$schema])) {
            $this->normalTables[$schema] = $this->findTableNames($schema, false);
        }
        $db = $this->connection;
        foreach ($this->normalTables[$schema] as $table) {
            $tableName = $this->quoteTableName($table->name);
            $db->createCommand("ALTER TABLE $tableName $enable CONSTRAINT ALL")->execute();
        }
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
        //if (!in_array($table->name, $this->tableNames)) return null;

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
        $parts = explode('.', str_replace(['[', ']'], '', $name));
        if (($c = count($parts)) == 2) {
            // Only schema name and table name provided
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
            // Only the name given, we need at least the default schema name
            $table->schemaName = $this->getDefaultSchema();
            $table->name = $parts[0];
            $table->rawName = $this->quoteTableName($table->schemaName) . '.' . $this->quoteTableName($table->name);
            $table->displayName = $table->name;
        }
    }

    /**
     * Collects the foreign key column details for the given table.
     * Also, collects the foreign tables and columns that reference the given table.
     *
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        $schema = (!empty($table->schemaName)) ? $table->schemaName : $this->getDefaultSchema();
        $defaultSchema = $this->getDefaultSchema();

        $sql = <<<EOD
SELECT indextype,colnames FROM SYS.SYSINDEXES WHERE creator = :schema AND tname = :table
EOD;
        $params = [':schema' => $schema, ':table' => $table->name];
        $columns = $this->connection->createCommand($sql)->queryAll(true, $params);

        foreach ($columns as $key => $column) {
            $type = $column['indextype'];
            $colnames = $column['colnames'];
            switch ($type) {
                case 'Primary Key':
                    $colnames = explode(',', $colnames);
                    switch (count($colnames)) {
                        case 0: // No primary key on table
                            $table->primaryKey = null;
                            break;
                        case 1: // Only 1 primary key
                            $primary = strstr($colnames[0], ' ', true);
                            $key = strtolower($primary);
                            if (isset($table->columns[$key])) {
                                $table->columns[$key]->isPrimaryKey = true;
                                if (('integer' === $table->columns[$key]->type) &&
                                    $table->columns[$key]->autoIncrement
                                ) {
                                    $table->columns[$key]->type = 'id';
                                }
                            }
                            $table->primaryKey = $primary;
                            break;
                        default:
                            if (is_array($colnames)) {
                                $primary = '';
                                foreach ($colnames as $key) {
                                    $key = strstr($key, ' ', true);
                                    $primary = (empty($key)) ? $key : ',' . $key;
                                }
                                $table->primaryKey = $primary;
                            }
                            break;
                    }
                    break;
                case 'Unique Constraint':
                    $field = strtolower(strstr($colnames, ' ', true));
                    if (isset($table->columns[$field])) {
                        $table->columns[$field]->IsUnique = true;
                    }
                    break;
                case 'Non-unique':
                    $colnames = explode(',', $colnames);
                    switch (count($colnames)) {
                        case 1: // Only 1 key
                            $field = strtolower(strstr($colnames[0], ' ', true));
                            if (isset($table->columns[$field])) {
                                $table->columns[$field]->isIndex = true;
                            }
                            break;
                        default:
                            if (is_array($colnames)) {
                                foreach ($colnames as $key) {
                                    $field = strtolower(strstr($key, ' ', true));
                                    if (isset($table->columns[$field])) {
                                        $table->columns[$field]->isIndex = true;
                                    }
                                }
                            }
                            break;
                    }
                    break;
            }
        }

        $sql = <<<EOD
SELECT * FROM SYS.SYSFOREIGNKEYS WHERE foreign_creator NOT IN ('SYS','dbo')
EOD;
        $columns = $columns2 = $this->connection->createCommand($sql)->queryAll();
        foreach ($columns as $key => $column) {
            list($cn, $rcn) = explode(' IS ', $column['columns']);
            $ts = $column['foreign_creator'];
            $tn = $column['foreign_tname'];
            $rts = $column['primary_creator'];
            $rtn = $column['primary_tname'];
            if ((0 == strcasecmp($tn, $table->name)) && (0 == strcasecmp($ts, $schema))) {
                $name = ($rts == $defaultSchema) ? $rtn : $rts . '.' . $rtn;

                $cnk = strtolower($cn);
                $table->foreignKeys[$cnk] = [$name, $rcn];
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
            } elseif ((0 == strcasecmp($rtn, $table->name)) && (0 == strcasecmp($rts, $schema))) {
                $name = ($ts == $defaultSchema) ? $tn : $ts . '.' . $tn;
                $table->addRelation('has_many', $name, $cn, $rcn);

                // if other has foreign keys to other tables, we can say these are related as well
                foreach ($columns2 as $key2 => $column2) {
                    if (0 != strcasecmp($key, $key2)) // not same key
                    {
                        $ts2 = $column2['foreign_creator'];
                        $tn2 = $column2['foreign_tname'];
                        list($cn2, $rcn2) = explode(' IS ', $column2['columns']);
                        if ((0 == strcasecmp($ts2, $ts)) && (0 == strcasecmp($tn2, $tn))
                        ) {
                            $rts2 = $column2['primary_creator'];
                            $rtn2 = $column2['primary_tname'];
                            if ((0 != strcasecmp($rts2, $schema)) || (0 != strcasecmp($rtn2, $table->name))
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
     * Collects the table column metadata.
     *
     * @param TableSchema $table the table metadata
     *
     * @return boolean whether the table exists in the database
     */
    protected function findColumns($table)
    {
        $sql = <<<SQL
SELECT * FROM sys.syscolumns WHERE creator = '{$table->schemaName}' AND tname = '{$table->name}'
SQL;

        try {
            $columns = $this->connection->createCommand($sql)->queryAll();
            if (empty($columns)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        foreach ($columns as $column) {
            $c = $this->createColumn($column);
            $table->addColumn($c);
            if ($c->autoIncrement && $table->sequenceName === null) {
                $table->sequenceName = $table->name;
            }
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
        $c = new ColumnSchema(['name' => $column['cname']]);
        $c->rawName = $this->quoteColumnName($c->name);
        $c->allowNull = $column['nulls'] == 'Y';
        $c->isPrimaryKey = $column['in_primary_key'] == 'Y';
        $c->dbType = $column['coltype'];
        $c->scale = intval($column['syslength']);
        $c->precision = $c->size = intval($column['length']);
        $c->comment = $column['remarks'];

        $c->extractFixedLength($column['coltype']);
        $c->extractMultiByteSupport($column['coltype']);
        $c->extractType($column['coltype']);
        if (isset($column['default_value'])) {
            $c->extractDefault($column['default_value']);
        }

        return $c;
    }

    protected function findSchemaNames()
    {
        $sql = <<<SQL
SELECT user_name FROM sysuser WHERE user_name NOT IN ('SYS','dbo') and user_type IN (12,13,14)
SQL;
        try {
            if (false === $names = $this->connection->createCommand($sql)->queryColumn()) {
                return [];
            }

            return $names;
        } catch (\Exception $e) {
            return [];
        }
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
        if ($include_views) {
            $condition = "tabletype IN ('TABLE','VIEW','MAT VIEW')";
        } else {
            $condition = "tabletype = 'TABLE'";
        }
        $params = [];
        if (!empty($schema)) {
            $condition .= " AND creator = :schema";
            $params[':schema'] = $schema;
        }

        $sql = <<<SQL
SELECT tname, tabletype, remarks FROM sys.syscatalog WHERE {$condition} ORDER BY tname
SQL;

        $defaultSchema = $this->getDefaultSchema();
        $rows = $this->connection->createCommand($sql)->queryAll(true, $params);

        $names = [];
        foreach ($rows as $row) {
            $name = isset($row['tname']) ? $row['tname'] : '';
            if (!empty($schema) && ($defaultSchema !== $schema)) {
                $name = $schema . '.' . $name;
            }
            $table = new TableNameSchema($name, ('TABLE' !== $row['tabletype']));
            $table->description = $row['remarks'];
            $names[strtolower($name)] = $table;
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
        $defaultSchema = $this->getDefaultSchema();
        $params = [];
        $where = null;
        if (!empty($schema)) {
            $where = 'WHERE creator = :schema';
            $params[':schema'] = $schema;
        }

        $sql = <<<SQL
SELECT procname FROM SYS.SYSPROCS {$where} ORDER BY procname
SQL;

        $results = $this->connection->createCommand($sql)->queryColumn($params);
        if (!empty($results) && !empty($schema) && ($defaultSchema != $schema)) {
            foreach ($results as $key => $name) {
                $results[$key] = $schema . '.' . $name;
            }
        }

        return $results;
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
        $name = $this->connection->quoteTableName($name);
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

        $this->connection->createCommand('SET QUOTED_IDENTIFIER ON; SET ANSI_WARNINGS ON;')->execute();
        $sql = "$pre EXEC $name $paramStr; $post";
        $command = $this->connection->createCommand($sql);

        // do binding
        $command->bindValues($bindings);

        $reader = $command->query();
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
//        $defaultSchema = $this->getDefaultSchema();
//        $params = [];
//        $where = null;
//        if (!empty($schema))
//        {
//            $where = 'WHERE creator = :schema';
//            $params[':schema'] = $schema;
//        }
//
//        $sql = <<<SQL
//SELECT procname FROM SYS.SYSPROCS {$where} ORDER BY procname
//SQL;
//
//        $results = $this->connection->createCommand($sql)->queryColumn($params);
//        if (!empty($results) && !empty($schema) && ($defaultSchema != $schema)) {
//            foreach ($results as $key => $name) {
//                $results[$key] = $schema . '.' . $name;
//            }
//        }
//
//        return $results;
        return [];
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
            $name = $this->getDefaultSchema() . '.' . $name;
        }
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
            do {
                $result[] = $reader->readAll();
            } while ($reader->nextResult());
        }

        return $result;
    }

    /**
     * Creates a command builder for the database.
     * This method overrides parent implementation in order to create a Sap specific command builder
     *
     * @return CommandBuilder command builder instance
     */
    protected function createCommandBuilder()
    {
        return new CommandBuilder($this);
    }

    /**
     * Builds a SQL statement for renaming a DB table.
     *
     * @param string $table   the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for renaming a DB table.
     * @since 1.1.6
     */
    public function renameTable($table, $newName)
    {
        return "sp_rename '$table', '$newName'";
    }

    /**
     * Builds a SQL statement for renaming a column.
     *
     * @param string $table   the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name    the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for renaming a DB column.
     * @since 1.1.6
     */
    public function renameColumn($table, $name, $newName)
    {
        return "sp_rename '$table.$name', '$newName', 'COLUMN'";
    }

    /**
     * Builds a SQL statement for changing the definition of a column.
     *
     * @param string $table      the table whose column is to be changed. The table name will be properly quoted by the
     *                           method.
     * @param string $column     the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $definition the new column type. The {@link getColumnType} method will be invoked to convert
     *                           abstract column type (if any) into the physical one. Anything that is not recognized
     *                           as abstract type will be kept in the generated SQL. For example, 'string' will be
     *                           turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not
     *                           null'.
     *
     * @return string the SQL statement for changing the definition of a column.
     * @since 1.1.6
     */
    public function alterColumn($table, $column, $definition)
    {
        $definition = $this->getColumnType($definition);
        $sql =
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' ALTER COLUMN ' .
            $this->quoteColumnName($column) .
            ' ' .
            $this->getColumnType($definition);

        return $sql;
    }

    /**
     * @param ColumnSchema $field_info
     * @param bool         $as_quoted_string
     * @param string       $out_as
     *
     * @return string
     */
    public function parseFieldForSelect($field_info, $as_quoted_string = false, $out_as = null)
    {
        $field = ($as_quoted_string) ? $this->quoteColumnName($field_info->name) : $field_info->name;
        $alias =
            ($as_quoted_string) ? $this->quoteColumnName($field_info->getName(true)) : $field_info->getName(true);
        switch ($field_info->dbType) {
            case 'datetime':
            case 'timestamp':
                return "(CONVERT(nvarchar(30), $field, 127)) AS $alias";
            case 'geometry':
            case 'geography':
            case 'hierarchyid':
                return "($field.ToString()) AS $alias";
            default :
                return parent::parseFieldForSelect($field_info, $as_quoted_string, $out_as);
        }
    }

    /**
     * @param bool $update
     *
     * @return mixed
     */
    public function getTimestampForSet($update = false)
    {
        return new Expression('(SYSDATETIMEOFFSET())');
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

    public static function formatValue($value, $type)
    {
        $value = parent::formatValue($value, $type);

        if (' ' === $value) {
            // SQL Anywhere strangely returns empty string as a single space string
            return '';
        }

        return $value;
    }
}
