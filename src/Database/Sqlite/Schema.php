<?php
namespace DreamFactory\Core\Database\Sqlite;

use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a SQLite (2/3) database.
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
                    $info['default'] = $default;
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
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    $info['default'] = floatval($default);
                }
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
                $info['type'] = 'blob';
                break;
        }
    }

    protected function validateColumnSettings(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
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
            $quoteDefault =
                (isset($info['quote_default'])) ? filter_var($info['quote_default'], FILTER_VALIDATE_BOOLEAN) : false;
            if ($quoteDefault) {
                $default = "'" . $default . "'";
            }

            $definition .= ' DEFAULT ' . $default;
        }

        $auto = (isset($info['auto_increment'])) ? filter_var($info['auto_increment'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($auto) {
            $definition .= ' AUTOINCREMENT';
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
        if ($value !== null) {
            $value = (int)($value) - 1;
        } else {
            $value = $this->connection
                ->createCommand("SELECT MAX(`{$table->primaryKey}`) FROM {$table->rawName}")
                ->queryScalar();
            $value = intval($value);
        }
        try {
            // it's possible that 'sqlite_sequence' does not exist
            $this->connection
                ->createCommand("UPDATE sqlite_sequence SET seq='$value' WHERE name='{$table->name}'")
                ->execute();
        } catch (\Exception $e) {
        }
    }

    /**
     * Enables or disables integrity check. Note that this method used to do nothing before 1.1.14. Since 1.1.14
     * it changes integrity check state as expected.
     *
     * @param boolean $check  whether to turn on or off the integrity check.
     * @param string  $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
     * @since 1.1
     */
    public function checkIntegrity($check = true, $schema = '')
    {
        $this->connection->createCommand('PRAGMA foreign_keys=' . (int)$check)->execute();
    }

    /**
     * Returns all table names in the database.
     *
     * @param string $schema the schema of the tables. This is not used for sqlite database.
     * @param bool   $include_views
     *
     * @return array all table names in the database.
     */
    protected function findTableNames($schema = '', $include_views = true)
    {
        $sql = "SELECT DISTINCT tbl_name FROM sqlite_master WHERE tbl_name<>'sqlite_sequence'";

        $rows = $this->connection->createCommand($sql)->queryColumn();

        $names = [];
        foreach ($rows as $row) {
            $names[strtolower($row)] = ['name' => $row, 'is_view' => false];
        }

        return $names;
    }

    /**
     * Creates a command builder for the database.
     *
     * @return CommandBuilder command builder instance
     */
    protected function createCommandBuilder()
    {
        return new CommandBuilder($this);
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
        $table->name = $name;
        $table->rawName = $this->quoteTableName($name);

        if ($this->findColumns($table)) {
            $this->findConstraints($table);

            return $table;
        } else {
            return null;
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
        $sql = "PRAGMA table_info({$table->rawName})";
        $columns = $this->connection->createCommand($sql)->queryAll();
        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            $c = $this->createColumn($column);
            $table->columns[$c->name] = $c;
            if ($c->isPrimaryKey) {
                if ($table->primaryKey === null) {
                    $table->primaryKey = $c->name;
                } elseif (is_string($table->primaryKey)) {
                    $table->primaryKey = [$table->primaryKey, $c->name];
                } else {
                    $table->primaryKey[] = $c->name;
                }
            }
        }
        if (is_string($table->primaryKey) && !strncasecmp($table->columns[$table->primaryKey]->dbType, 'int', 3)) {
            $table->sequenceName = '';
            $table->columns[$table->primaryKey]->autoIncrement = true;
        }

        return true;
    }

    /**
     * Collects the foreign key column details for the given table.
     *
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        $foreignKeys = [];
        $sql = "PRAGMA foreign_key_list({$table->rawName})";
        $keys = $this->connection->createCommand($sql)->queryAll();
        foreach ($keys as $key) {
            $column = $table->columns[$key['from']];
            $column->isForeignKey = true;
            $column->refTable = $key['table'];
            $column->refFields = $key['to'];
            if ('integer' === $column->type) {
                $column->type = 'reference';
            }
            $foreignKeys[$key['from']] = [$key['table'], $key['to']];
        }
        $table->foreignKeys = $foreignKeys;
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
        $c = new ColumnSchema(['name' => $column['name']]);
        $c->rawName = $this->quoteColumnName($c->name);
        $c->allowNull = !$column['notnull'];
        $c->isPrimaryKey = $column['pk'] != 0;
        $c->comment = null; // SQLite does not support column comments at all

        $c->dbType = strtolower($column['type']);
        $c->extractLimit(strtolower($column['type']));
        $c->extractFixedLength($column['type']);
        $c->extractMultiByteSupport($column['type']);
        $c->extractType(strtolower($column['type']));
        $c->extractDefault($column['dflt_value']);

        return $c;
    }

    /**
     * Builds a SQL statement for renaming a DB table.
     *
     * @param string $table   the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for renaming a DB table.
     * @since 1.1.13
     */
    public function renameTable($table, $newName)
    {
        return 'ALTER TABLE ' . $this->quoteTableName($table) . ' RENAME TO ' . $this->quoteTableName($newName);
    }

    /**
     * Builds a SQL statement for truncating a DB table.
     *
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for truncating a DB table.
     * @since 1.1.6
     */
    public function truncateTable($table)
    {
        return "DELETE FROM " . $this->quoteTableName($table);
    }

    /**
     * Builds a SQL statement for dropping a DB column.
     * Because SQLite does not support dropping a DB column, calling this method will throw an exception.
     *
     * @param string $table  the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     *
     * @throws \Exception
     * @return string the SQL statement for dropping a DB column.
     * @since 1.1.6
     */
    public function dropColumn($table, $column)
    {
        throw new \Exception('Dropping DB column is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for renaming a column.
     * Because SQLite does not support renaming a DB column, calling this method will throw an exception.
     *
     * @param string $table   the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $name    the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     *
     * @throws \Exception
     * @return string the SQL statement for renaming a DB column.
     * @since 1.1.6
     */
    public function renameColumn($table, $name, $newName)
    {
        throw new \Exception('Renaming a DB column is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * Because SQLite does not support adding foreign key to an existing table, calling this method will throw an
     * exception.
     *
     * @param string $name       the name of the foreign key constraint.
     * @param string $table      the table that the foreign key constraint will be added to.
     * @param string $columns    the name of the column to that the constraint will be added on. If there are multiple
     *                           columns, separate them with commas.
     * @param string $refTable   the table that the foreign key references to.
     * @param string $refColumns the name of the column that the foreign key references to. If there are multiple
     *                           columns, separate them with commas.
     * @param string $delete     the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION,
     *                           SET DEFAULT, SET NULL
     * @param string $update     the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION,
     *                           SET DEFAULT, SET NULL
     *
     * @throws \Exception
     * @return string the SQL statement for adding a foreign key constraint to an existing table.
     * @since 1.1.6
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        throw new \Exception('Adding a foreign key constraint to an existing table is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     * Because SQLite does not support dropping a foreign key constraint, calling this method will throw an exception.
     *
     * @param string $name  the name of the foreign key constraint to be dropped. The name will be properly quoted by
     *                      the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     *
     * @throws \Exception
     * @return string the SQL statement for dropping a foreign key constraint.
     * @since 1.1.6
     */
    public function dropForeignKey($name, $table)
    {
        throw new \Exception('Dropping a foreign key constraint is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for changing the definition of a column.
     * Because SQLite does not support altering a DB column, calling this method will throw an exception.
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
     * @throws \Exception
     * @return string the SQL statement for changing the definition of a column.
     * @since 1.1.6
     */
    public function alterColumn($table, $column, $definition)
    {
        throw new \Exception('Altering a DB column is not supported by SQLite.');
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
     * Builds a SQL statement for adding a primary key constraint to an existing table.
     * Because SQLite does not support adding a primary key on an existing table this method will throw an exception.
     *
     * @param string       $name    the name of the primary key constraint.
     * @param string       $table   the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     *
     * @throws \Exception
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     * @since 1.1.13
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        throw new \Exception('Adding a primary key after table has been created is not supported by SQLite.');
    }

    /**
     * Builds a SQL statement for removing a primary key constraint to an existing table.
     * Because SQLite does not support dropping a primary key from an existing table this method will throw an exception
     *
     * @param string $name  the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     *
     * @throws \Exception
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     * @since 1.1.13
     */
    public function dropPrimaryKey($name, $table)
    {
        throw new \Exception('Removing a primary key after table has been created is not supported by SQLite.');
    }
}
