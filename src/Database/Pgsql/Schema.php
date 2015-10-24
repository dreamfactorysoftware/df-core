<?php
namespace DreamFactory\Core\Database\Pgsql;

use DreamFactory\Core\Database\TableNameSchema;
use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a PostgreSQL database.
 */
class Schema extends \DreamFactory\Core\Database\Schema
{
    const DEFAULT_SCHEMA = 'public';

    private $sequences = [];

    /**
     * @param boolean $refresh if we need to refresh schema cache.
     *
     * @return string default schema.
     */
    public function getDefaultSchema($refresh = false)
    {
        return static::DEFAULT_SCHEMA;
    }

    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'pk':
            case 'id':
                $info['type'] = 'serial';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case 'reference':
                $info['type'] = 'integer';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case 'datetime':
                $info['type'] = 'timestamp';
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
                    $info['default'] = $default;
                }
                break;

            case 'user_id':
            case 'user_id_on_create':
            case 'user_id_on_update':
                $info['type'] = 'integer';
                break;

            case 'int':
                $info['type'] = 'integer';
                break;

            case 'float':
                $info['type'] = 'real';
                break;

            case 'double':
                $info['type'] = 'double precision';
                break;

            case 'string':
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $national =
                    (isset($info['supports_multibyte'])) ? filter_var($info['supports_multibyte'],
                        FILTER_VALIDATE_BOOLEAN) : false;
                if ($fixed) {
                    $info['type'] = ($national) ? 'national char' : 'char';
                } elseif ($national) {
                    $info['type'] = 'national varchar';
                } else {
                    $info['type'] = 'varchar';
                }
                break;

            case 'binary':
                $info['type'] = 'bytea';
                break;
        }
    }

    protected function validateColumnSettings(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'boolean':
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (filter_var($default, FILTER_VALIDATE_BOOLEAN)) ? 'TRUE' : 'FALSE';
                }
                break;

            case 'smallint':
            case 'integer':
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
            case 'double precision':
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
            case 'national char':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;

            case 'varchar':
            case 'national varchar':
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
            $quoteDefault =
                (isset($info['quote_default'])) ? filter_var($info['quote_default'], FILTER_VALIDATE_BOOLEAN) : false;
            if ($quoteDefault) {
                $default = "'" . $default . "'";
            }

            $definition .= ' DEFAULT ' . $default;
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
        return '"' . $name . '"';
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
     * @since 1.1
     */
    public function resetSequence($table, $value = null)
    {
        if ($table->sequenceName === null) {
            return;
        }
        $sequence = '"' . $table->sequenceName . '"';
        if (strpos($sequence, '.') !== false) {
            $sequence = str_replace('.', '"."', $sequence);
        }
        if ($value !== null) {
            $value = (int)$value;
        } else {
            $value = "(SELECT COALESCE(MAX(\"{$table->primaryKey}\"),0) FROM {$table->rawName})+1";
        }
        $this->connection->createCommand("SELECT SETVAL('$sequence',$value,false)")->execute();
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
        $enable = $check ? 'ENABLE' : 'DISABLE';
        $tableNames = $this->getTableNames($schema);
        $db = $this->connection;
        foreach ($tableNames as $tableInfo) {
            $tableName = $tableInfo['name'];
            $tableName = '"' . $tableName . '"';
            if (strpos($tableName, '.') !== false) {
                $tableName = str_replace('.', '"."', $tableName);
            }
            $db->createCommand("ALTER TABLE $tableName $enable TRIGGER ALL")->execute();
        }
    }

    /**
     * Loads the metadata for the specified table.
     *
     * @param string $name table name
     *
     * @return TableSchema driver dependent table metadata.
     */
    protected function loadTable($name)
    {
        $table = new TableSchema($name);
        $this->resolveTableNames($table, $name);
        if (!$this->findColumns($table)) {
            return null;
        }
        $this->findConstraints($table);

        if (is_string($table->primaryKey) && isset($this->sequences[$table->rawName . '.' . $table->primaryKey])) {
            $table->sequenceName = $this->sequences[$table->rawName . '.' . $table->primaryKey];
        } elseif (is_array($table->primaryKey)) {
            foreach ($table->primaryKey as $pk) {
                if (isset($this->sequences[$table->rawName . '.' . $pk])) {
                    $table->sequenceName = $this->sequences[$table->rawName . '.' . $pk];
                    break;
                }
            }
        }

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
        $parts = explode('.', str_replace('"', '', $name));
        if (isset($parts[1])) {
            $schemaName = $parts[0];
            $tableName = $parts[1];
        } else {
            $schemaName = self::DEFAULT_SCHEMA;
            $tableName = $parts[0];
        }

        $table->name = $tableName;
        $table->schemaName = $schemaName;
        if ($schemaName === self::DEFAULT_SCHEMA) {
            $table->rawName = $this->quoteTableName($tableName);
            $table->displayName = $tableName;
        } else {
            $table->rawName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($tableName);
            $table->rawName = $schemaName . '.' . $tableName;
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
        $sql = <<<EOD
SELECT a.attname, LOWER(format_type(a.atttypid, a.atttypmod)) AS type, d.adsrc, a.attnotnull, a.atthasdef,
	pg_catalog.col_description(a.attrelid, a.attnum) AS comment
FROM pg_attribute a LEFT JOIN pg_attrdef d ON a.attrelid = d.adrelid AND a.attnum = d.adnum
WHERE a.attnum > 0 AND NOT a.attisdropped
	AND a.attrelid = (SELECT oid FROM pg_catalog.pg_class WHERE relname=:table
		AND relnamespace = (SELECT oid FROM pg_catalog.pg_namespace WHERE nspname = :schema))
ORDER BY a.attnum
EOD;
        $command = $this->connection->createCommand($sql);
        $command->bindValue(':table', $table->name);
        $command->bindValue(':schema', $table->schemaName);

        if (($columns = $command->queryAll()) === []) {
            return false;
        }

        foreach ($columns as $column) {
            $c = $this->createColumn($column);
            $table->addColumn($c);

            if (stripos($column['adsrc'], 'nextval') === 0 &&
                preg_match('/nextval\([^\']*\'([^\']+)\'[^\)]*\)/i', $column['adsrc'], $matches)
            ) {
                if (strpos($matches[1], '.') !== false || $table->schemaName === self::DEFAULT_SCHEMA) {
                    $this->sequences[$table->rawName . '.' . $c->name] = $matches[1];
                } else {
                    $this->sequences[$table->rawName . '.' . $c->name] = $table->schemaName . '.' . $matches[1];
                }
                $c->autoIncrement = true;
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
        $c = new ColumnSchema(['name' => $column['attname']]);
        $c->rawName = $this->quoteColumnName($c->name);
        $c->allowNull = !$column['attnotnull'];
        $c->comment = $column['comment'] === null ? '' : $column['comment'];
        $c->dbType = $column['type'];
        $c->extractLimit($column['type']);
        $c->extractFixedLength($column['type']);
        $c->extractMultiByteSupport($column['type']);
        $c->extractType($column['type']);
        $c->extractDefault($column['atthasdef'] ? $column['adsrc'] : null);

        return $c;
    }

    /**
     * Collects the primary and foreign key column details for the given table.
     *
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        $this->findPrimaryKey($table);

        $schema = (!empty($table->schemaName)) ? $table->schemaName : static::DEFAULT_SCHEMA;
        $rc = 'information_schema.referential_constraints';
        $kcu = 'information_schema.key_column_usage';
        if (isset($table->catalogName)) {
            $kcu = $table->catalogName . '.' . $kcu;
            $rc = $table->catalogName . '.' . $rc;
        }

        $sql = <<<EOD
		SELECT
		     KCU1.TABLE_SCHEMA AS table_schema
		   , KCU1.TABLE_NAME AS table_name
		   , KCU1.COLUMN_NAME AS column_name
		   , KCU2.TABLE_SCHEMA AS referenced_table_schema
		   , KCU2.TABLE_NAME AS referenced_table_name
		   , KCU2.COLUMN_NAME AS referenced_column_name
		FROM {$this->quoteTableName($rc)} RC
		JOIN {$this->quoteTableName($kcu)} KCU1
		ON KCU1.CONSTRAINT_CATALOG = RC.CONSTRAINT_CATALOG
		   AND KCU1.CONSTRAINT_SCHEMA = RC.CONSTRAINT_SCHEMA
		   AND KCU1.CONSTRAINT_NAME = RC.CONSTRAINT_NAME
		JOIN {$this->quoteTableName($kcu)} KCU2
		ON KCU2.CONSTRAINT_CATALOG = RC.UNIQUE_CONSTRAINT_CATALOG
		   AND KCU2.CONSTRAINT_SCHEMA =	RC.UNIQUE_CONSTRAINT_SCHEMA
		   AND KCU2.CONSTRAINT_NAME = RC.UNIQUE_CONSTRAINT_NAME
		   AND KCU2.ORDINAL_POSITION = KCU1.ORDINAL_POSITION
EOD;

        $columns = $columns2 = $this->connection->createCommand($sql)->queryAll();

        foreach ($columns as $key => $column) {
            $ts = $column['table_schema'];
            $tn = $column['table_name'];
            $cn = $column['column_name'];
            $rts = $column['referenced_table_schema'];
            $rtn = $column['referenced_table_name'];
            $rcn = $column['referenced_column_name'];
            if ((0 == strcasecmp($tn, $table->name)) && (0 == strcasecmp($ts, $schema))) {
                $name = ($rts == static::DEFAULT_SCHEMA) ? $rtn : $rts . '.' . $rtn;

                $table->foreignKeys[$cn] = [$name, $rcn];
                if (isset($table->columns[$cn])) {
                    $table->columns[$cn]->isForeignKey = true;
                    $table->columns[$cn]->refTable = $name;
                    $table->columns[$cn]->refFields = $rcn;
                    if ('integer' === $table->columns[$cn]->type) {
                        $table->columns[$cn]->type = 'reference';
                    }
                }

                // Add it to our foreign references as well
                $table->addRelation('belongs_to', $name, $rcn, $cn);
            } elseif ((0 == strcasecmp($rtn, $table->name)) && (0 == strcasecmp($rts, $schema))) {
                $name = ($ts == static::DEFAULT_SCHEMA) ? $tn : $ts . '.' . $tn;
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
                            if ((0 != strcasecmp($rts2, $schema)) || (0 != strcasecmp($rtn2, $table->name))
                            ) {
                                $name2 = ($rts2 == static::DEFAULT_SCHEMA) ? $rtn2 : $rts2 . '.' . $rtn2;
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
     * Gets the primary key column(s) details for the given table.
     *
     * @param TableSchema $table table
     *
     * @return mixed primary keys (null if no pk, string if only 1 column pk, or array if composite pk)
     */
    protected function findPrimaryKey($table)
    {
        $kcu = 'information_schema.key_column_usage';
        $tc = 'information_schema.table_constraints';
        if (isset($table->catalogName)) {
            $kcu = $table->catalogName . '.' . $kcu;
            $tc = $table->catalogName . '.' . $tc;
        }

        $sql = <<<EOD
		SELECT k.column_name field_name
			FROM {$this->quoteTableName($kcu)} k
		    LEFT JOIN {$this->quoteTableName($tc)} c
		      ON k.table_name = c.table_name
		     AND k.constraint_name = c.constraint_name
		   WHERE c.constraint_type ='PRIMARY KEY'
		   	    AND k.table_name = :table
				AND k.table_schema = :schema
EOD;
        $command = $this->connection->createCommand($sql);
        $command->bindValue(':table', $table->name);
        $command->bindValue(':schema', $table->schemaName);

        $table->primaryKey = null;
        foreach ($command->queryAll() as $row) {
            $name = $row['field_name'];
            if (isset($table->columns[$name])) {
                $table->columns[$name]->isPrimaryKey = true;
                if (('integer' === $table->columns[$name]->type) && $table->columns[$name]->autoIncrement) {
                    $table->columns[$name]->type = 'id';
                }
                if ($table->primaryKey === null) {
                    $table->primaryKey = $name;
                } elseif (is_string($table->primaryKey)) {
                    $table->primaryKey = [$table->primaryKey, $name];
                } else {
                    $table->primaryKey[] = $name;
                }
            }
        }
    }

    protected function findSchemaNames()
    {
        $sql = <<<SQL
SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','pg_catalog')
SQL;
        $rows = $this->connection->createCommand($sql)->queryColumn();

        if (false === array_search(static::DEFAULT_SCHEMA, $rows)) {
            $rows[] = static::DEFAULT_SCHEMA;
        }

        return $rows;
    }

    /**
     * Returns all table names in the database.
     *
     * @param string  $schema        the schema of the tables. Defaults to empty string, meaning the current or default
     *                               schema. If not empty, the returned table names will be prefixed with the schema
     *                               name.
     * @param boolean $include_views whether to include views in the result. Defaults to true.
     *
     * @return array all table names in the database.
     */
    protected function findTableNames($schema = '', $include_views = true)
    {
        if ($include_views) {
            $condition = "table_type in ('BASE TABLE','VIEW')";
        } else {
            $condition = "table_type = 'BASE TABLE'";
        }

        $sql = <<<EOD
SELECT table_name, table_schema, table_type FROM information_schema.tables
WHERE $condition
EOD;

        if (!empty($schema)) {
            $sql .= " AND table_schema = '$schema'";
        }

        $defaultSchema = self::DEFAULT_SCHEMA;

        $rows = $this->connection->createCommand($sql)->queryAll();

        $names = [];
        foreach ($rows as $row) {
            $schema = isset($row['table_schema']) ? $row['table_schema'] : '';
            $name = isset($row['table_name']) ? $row['table_name'] : '';
            if ($defaultSchema !== $schema) {
                $name = $schema . '.' . $name;
            }
            $names[strtolower($name)] = new TableNameSchema($name, (0 === strcasecmp('VIEW', $row['table_type'])));
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
     * @return mixed
     * @throws \Exception
     */
    public function callProcedure($name, &$params)
    {
        $name = $this->connection->quoteTableName($name);
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
        $command = $this->connection->createCommand($sql);

        // do binding
        $command->bindValues($bindings);

        // driver does not support multiple result sets currently
        $result = $command->queryAll();

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
        $sql = "SELECT * FROM $name($paramStr)";
        $command = $this->connection->createCommand($sql);

        // do binding
        $command->bindValues($bindings);

        // driver does not support multiple result sets currently
        $result = $command->queryAll();

        return $result;
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

        $select =
            (empty($schema) || ($defaultSchema == $schema))
                ? 'ROUTINE_NAME' : "CONCAT('" . $schema . "','.',ROUTINE_NAME) as ROUTINE_NAME";
        $schema = !empty($schema) ? " WHERE ROUTINE_SCHEMA = '" . $schema . "'" : null;

        $sql = <<<MYSQL
SELECT
    {$select}
FROM
    information_schema.ROUTINES
    {$schema}
MYSQL;

        return $this->connection->createCommand($sql)->queryColumn();
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
        return 'ALTER TABLE ' . $this->quoteTableName($table) . ' RENAME TO ' . $this->quoteTableName($newName);
    }

    /**
     * Builds a SQL statement for adding a new DB column.
     *
     * @param string $table  the table that the new column will be added to. The table name will be properly quoted by
     *                       the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type   the column type. The {@link getColumnType} method will be invoked to convert abstract
     *                       column type (if any) into the physical one. Anything that is not recognized as abstract
     *                       type will be kept in the generated SQL. For example, 'string' will be turned into
     *                       'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     *
     * @return string the SQL statement for adding a new column.
     * @since 1.1.6
     */
    public function addColumn($table, $column, $type)
    {
        $type = $this->getColumnType($type);
        $sql =
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' ADD COLUMN ' .
            $this->quoteColumnName($column) .
            ' ' .
            $type;

        return $sql;
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
        $sql = 'ALTER TABLE ' . $this->quoteTableName($table) . ' ALTER COLUMN ' . $this->quoteColumnName($column);
        if (false !== $pos = strpos($definition, ' ')) {
            $sql .= ' TYPE ' . $this->getColumnType(substr($definition, 0, $pos));
            switch (substr($definition, $pos + 1)) {
                case 'NULL':
                    $sql .= ', ALTER COLUMN ' . $this->quoteColumnName($column) . ' DROP NOT NULL';
                    break;
                case 'NOT NULL':
                    $sql .= ', ALTER COLUMN ' . $this->quoteColumnName($column) . ' SET NOT NULL';
                    break;
            }
        } else {
            $sql .= ' TYPE ' . $this->getColumnType($definition);
        }

        return $sql;
    }

    /**
     * Builds a SQL statement for creating a new index.
     *
     * @param string  $name    the name of the index. The name will be properly quoted by the method.
     * @param string  $table   the table that the new index will be created for. The table name will be properly quoted
     *                         by the method.
     * @param string  $columns the column(s) that should be included in the index. If there are multiple columns,
     *                         please separate them by commas. Each column name will be properly quoted by the method,
     *                         unless a parenthesis is found in the name.
     * @param boolean $unique  whether to add UNIQUE constraint on the created index.
     *
     * @return string the SQL statement for creating a new index.
     * @since 1.1.6
     */
    public function createIndex($name, $table, $columns, $unique = false)
    {
        $cols = [];
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach ($columns as $col) {
            if (strpos($col, '(') !== false) {
                $cols[] = $col;
            } else {
                $cols[] = $this->quoteColumnName($col);
            }
        }
        if ($unique) {
            return
                'ALTER TABLE ONLY ' .
                $this->quoteTableName($table) .
                ' ADD CONSTRAINT ' .
                $this->quoteTableName($name) .
                ' UNIQUE (' .
                implode(', ', $cols) .
                ')';
        } else {
            return
                'CREATE INDEX ' .
                $this->quoteTableName($name) .
                ' ON ' .
                $this->quoteTableName($table) .
                ' (' .
                implode(', ', $cols) .
                ')';
        }
    }

    /**
     * Builds a SQL statement for dropping an index.
     *
     * @param string $name  the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for dropping an index.
     * @since 1.1.6
     */
    public function dropIndex($name, $table)
    {
        return 'DROP INDEX ' . $this->quoteTableName($name);
    }

    /**
     * Creates a command builder for the database.
     * This method may be overridden by child classes to create a DBMS-specific command builder.
     *
     * @return CommandBuilder command builder instance.
     */
    protected function createCommandBuilder()
    {
        return new CommandBuilder($this);
    }

    public function parseValueForSet($value, $field_info)
    {
        switch ($field_info->type) {
            case 'boolean':
                $value = (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE');
                break;
        }

        return $value;
    }

    public static function formatValue($value, $type)
    {
        if (('int' === $type) && ('' === $value)) {
            // Postgresql strangely returns "" for null integers
            return null;
        }

        return parent::formatValue($value, $type);
    }
}
