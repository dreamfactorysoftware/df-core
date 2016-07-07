<?php
namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Core\Enums\DbSimpleTypes;

/**
 * Schema is the class for retrieving metadata information from a PostgreSQL database.
 */
class PostgresSchema extends Schema
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
            case DbSimpleTypes::TYPE_ID:
                $info['type'] = 'serial';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case DbSimpleTypes::TYPE_REF:
                $info['type'] = 'integer';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case 'datetime':
                $info['type'] = 'timestamp';
                break;

            case DbSimpleTypes::TYPE_TIMESTAMP_ON_CREATE:
            case DbSimpleTypes::TYPE_TIMESTAMP_ON_UPDATE:
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $default = 'CURRENT_TIMESTAMP';
                    // ON UPDATE CURRENT_TIMESTAMP not supported by PostgreSQL, use triggers
                    $info['default'] = $default;
                }
                break;

            case DbSimpleTypes::TYPE_USER_ID:
            case DbSimpleTypes::TYPE_USER_ID_ON_CREATE:
            case DbSimpleTypes::TYPE_USER_ID_ON_UPDATE:
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

            case DbSimpleTypes::TYPE_STRING:
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

            case DbSimpleTypes::TYPE_BINARY:
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
     * Resets the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or max value of a primary key plus one (i.e. sequence trimming).
     *
     * @param TableSchema  $table   the table schema whose primary key sequence will be reset
     * @param integer|null $value   the value for the primary key of the next new row inserted.
     *                              If this is not set, the next new row's primary key will have the max value of a
     *                              primary key plus one (i.e. sequence trimming).
     *
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
        $this->connection->statement("SELECT SETVAL('$sequence',$value,false)");
    }

    /**
     * Enables or disables integrity check.
     *
     * @param boolean $check  whether to turn on or off the integrity check.
     * @param string  $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
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
            $db->statement("ALTER TABLE $tableName $enable TRIGGER ALL");
        }
    }

    /**
     * @inheritdoc
     */
    protected function loadTable(TableSchema $table)
    {
        if (!$this->findColumns($table)) {
            return null;
        }
        $this->findConstraints($table);

        if (is_string($table->primaryKey) && isset($this->sequences[$table->rawName . '.' . $table->primaryKey])) {
//            $table->sequenceName = $this->sequences[$table->rawName . '.' . $table->primaryKey];
            $table->sequenceName = $table->primaryKey;
        } elseif (is_array($table->primaryKey)) {
            foreach ($table->primaryKey as $pk) {
                if (isset($this->sequences[$table->rawName . '.' . $pk])) {
//                    $table->sequenceName = $this->sequences[$table->rawName . '.' . $pk];
                    $table->sequenceName = $pk;
                    break;
                }
            }
        }

        return $table;
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
        $columns = $this->connection->select($sql, [':table' => $table->tableName, ':schema' => $table->schemaName]);

        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            $column = (array)$column;
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
        $this->extractLimit($c, $column['type']);
        $c->fixedLength = $this->extractFixedLength($column['type']);
        $c->supportsMultibyte = $this->extractMultiByteSupport($column['type']);
        $this->extractType($c, $column['type']);
        $this->extractDefault($c, $column['atthasdef'] ? $column['adsrc'] : null);

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

        $constraints = $this->connection->select($sql);

        $this->buildTableRelations($table, $constraints);
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
        $rows = $this->connection->select($sql, [':table' => $table->tableName, ':schema' => $table->schemaName]);

        $table->primaryKey = null;
        foreach ($rows as $row) {
            $row = (array)$row;
            $name = $row['field_name'];
            $column = $table->getColumn($name);
            if (isset($column)) {
                $column->isPrimaryKey = true;
                if ((DbSimpleTypes::TYPE_INTEGER === $column->type) && $column->autoIncrement) {
                    $column->type = DbSimpleTypes::TYPE_ID;
                }
                if ($table->primaryKey === null) {
                    $table->primaryKey = $name;
                } elseif (is_string($table->primaryKey)) {
                    $table->primaryKey = [$table->primaryKey, $name];
                } else {
                    $table->primaryKey[] = $name;
                }
                // update the column in the table
                $table->addColumn($column);
            }
        }
    }

    protected function findSchemaNames()
    {
        $sql = <<<MYSQL
SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('information_schema','pg_catalog')
MYSQL;
        $rows = $this->selectColumn($sql);

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
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $rows = $this->connection->select($sql);

        $names = [];
        foreach ($rows as $row) {
            $row = (array)$row;
            $schemaName = isset($row['table_schema']) ? $row['table_schema'] : '';
            $tableName = isset($row['table_name']) ? $row['table_name'] : '';
            $isView = (0 === strcasecmp('VIEW', $row['table_type']));
            if ($addSchema) {
                $name = $schemaName . '.' . $tableName;
                $rawName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($tableName);;
            } else {
                $name = $tableName;
                $rawName = $this->quoteTableName($tableName);
            }
            $settings = compact('schemaName', 'tableName', 'name', 'rawName', 'isView');

            $names[strtolower($name)] = new TableSchema($settings);
        }

        return $names;
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

    public function parseValueForSet($value, $field_info)
    {
        switch ($field_info->type) {
            case DbSimpleTypes::TYPE_BOOLEAN:
                $value = (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE');
                break;
        }

        return $value;
    }

    public function formatValue($value, $type)
    {
        switch (strtolower(strval($type))) {
            case 'int':
            case 'integer':
                if ('' === $value) {
                    // Postgresql strangely returns "" for null integers
                    return null;
                }
        }

        return parent::formatValue($value, $type);
    }

    /**
     * Extracts the PHP type from DB type.
     *
     * @param string $dbType DB type
     */
    public function extractType(ColumnSchema &$column, $dbType)
    {
        parent::extractType($column, $dbType);
        if (strpos($dbType, '[') !== false || strpos($dbType, 'char') !== false || strpos($dbType, 'text') !== false) {
            $column->type = DbSimpleTypes::TYPE_STRING;
        } elseif (preg_match('/(real|float|double)/', $dbType)) {
            $column->type = DbSimpleTypes::TYPE_DOUBLE;
        } elseif (preg_match('/(integer|oid|serial|smallint)/', $dbType)) {
            $column->type = DbSimpleTypes::TYPE_INTEGER;
        }
    }

    /**
     * Extracts the PHP type from DF type.
     *
     * @param string $type DF type
     *
     * @return string
     */
    public static function extractPhpType($type)
    {
        switch ($type) {
            case DbSimpleTypes::TYPE_MONEY:
                return 'string';
        }

        return parent::extractPhpType($type);
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     *
     * @param ColumnSchema $field
     * @param string       $dbType the column's DB type
     */
    public function extractLimit(ColumnSchema &$field, $dbType)
    {
        if (strpos($dbType, '(')) {
            if (preg_match('/^time.*\((.*)\)/', $dbType, $matches)) {
                $field->precision = (int)$matches[1];
            } elseif (preg_match('/\((.*)\)/', $dbType, $matches)) {
                $values = explode(',', $matches[1]);
                $field->size = $field->precision = (int)$values[0];
                if (isset($values[1])) {
                    $field->scale = (int)$values[1];
                }
            }
        }
    }

    /**
     * Extracts the default value for the column.
     * The value is typecasted to correct PHP type.
     *
     * @param ColumnSchema $field
     * @param mixed        $defaultValue the default value obtained from metadata
     */
    public function extractDefault(ColumnSchema &$field, $defaultValue)
    {
        if ($defaultValue === 'true') {
            $field->defaultValue = true;
        } elseif ($defaultValue === 'false') {
            $field->defaultValue = false;
        } elseif (strpos($defaultValue, 'nextval') === 0) {
            $field->defaultValue = null;
        } elseif (preg_match('/^\'(.*)\'::/', $defaultValue, $matches)) {
            $field->defaultValue = $this->typecast($field, str_replace("''", "'", $matches[1]));
        } elseif (preg_match('/^(-?\d+(\.\d*)?)(::.*)?$/', $defaultValue, $matches)) {
            $field->defaultValue = $this->typecast($field, $matches[1]);
        } else {
            // could be a internal function call like setting uuids
            $field->defaultValue = $defaultValue;
        }
    }

    /**
     * @inheritdoc
     */
    protected function findRoutineNames($type, $schema = '')
    {
        $bindings = [];
        $where = '';
        if (!empty($schema)) {
            $where .= 'WHERE ROUTINE_SCHEMA = :schema';
            $bindings[':schema'] = $schema;
        }

        $sql = <<<MYSQL
SELECT ROUTINE_NAME, DATA_TYPE FROM INFORMATION_SCHEMA.ROUTINES {$where}
MYSQL;

        $rows = $this->connection->select($sql, $bindings);

        $sql = <<<MYSQL
SELECT r.ROUTINE_NAME
FROM INFORMATION_SCHEMA.PARAMETERS AS p JOIN INFORMATION_SCHEMA.ROUTINES AS r ON r.SPECIFIC_NAME = p.SPECIFIC_NAME 
WHERE p.SPECIFIC_SCHEMA = :schema AND (p.PARAMETER_MODE = 'INOUT' OR p.PARAMETER_MODE = 'OUT')
MYSQL;

        $procedures = $this->selectColumn($sql, $bindings);

        $defaultSchema = $this->getDefaultSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $name = array_get($row, 'ROUTINE_NAME');
            switch (strtoupper($type)) {
                case 'PROCEDURE':
                    if (false === array_search($name, $procedures)) {
                        // only way to determine proc from func is by params??
                        continue 2;
                    }
                    break;
                case 'FUNCTION':
                    if (false !== array_search($name, $procedures)) {
                        // only way to determine proc from func is by params??
                        continue 2;
                    }
                    break;
            }
            $schemaName = $schema;
            if ($addSchema) {
                $publicName = $schemaName . '.' . $name;
                $rawName = $this->quoteTableName($schemaName) . '.' . $this->quoteTableName($name);;
            } else {
                $publicName = $name;
                $rawName = $this->quoteTableName($name);
            }
            $returnType = array_get($row, 'DATA_TYPE');
            if (!empty($returnType) && (0 !== strcasecmp('void', $returnType))) {
                $returnType = static::extractSimpleType($returnType);
            }
            $settings = compact('schemaName', 'name', 'publicName', 'rawName', 'returnType');
            $names[strtolower($publicName)] =
                ('PROCEDURE' === $type) ? new ProcedureSchema($settings) : new FunctionSchema($settings);
        }

        return $names;
    }

    protected function doRoutineBinding($statement, array $paramSchemas, array &$values)
    {
        // do binding
        foreach ($paramSchemas as $key => $paramSchema) {
            switch ($paramSchema->paramType) {
                case 'IN':
                case 'INOUT':
                    $this->bindValue($statement, ':' . $paramSchema->name, array_get($values, $key));
                    break;
                case 'OUT':
                    // not sent as parameters, but pulled from fetch results
                    break;
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function getRoutineParamString(array $param_schemas, array &$values)
    {
        $paramStr = '';
        foreach ($param_schemas as $key => $paramSchema) {
            switch ($paramSchema->paramType) {
                case 'IN':
                case 'INOUT':
                    $pName = ':' . $paramSchema->name;
                    $paramStr .= (empty($paramStr)) ? $pName : ", $pName";
                    break;
                case 'OUT':
                    // not sent as parameters, but pulled from fetch results
                    break;
                default:
                    break;
            }
        }

        return $paramStr;
    }

    protected function getProcedureStatement(RoutineSchema $routine, array $param_schemas, array &$values)
    {
        $paramStr = $this->getRoutineParamString($param_schemas, $values);

        return "SELECT * FROM {$routine->rawName}($paramStr);";
    }

    /**
     * @inheritdoc
     */
    protected function getFunctionStatement(RoutineSchema $routine, array $param_schemas, array &$values)
    {
        $paramStr = $this->getRoutineParamString($param_schemas, $values);

        return "SELECT * FROM {$routine->rawName}($paramStr)";
    }

    protected function handleRoutineException(\Exception $ex)
    {
        if (false !== stripos($ex->getMessage(), 'does not support multiple rowsets')) {
            return true;
        }

        return false;
    }
}
