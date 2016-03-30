<?php
namespace DreamFactory\Core\Database\Oci;

use DreamFactory\Core\Database\Expression;
use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from an Oracle database.
 */
class Schema extends \DreamFactory\Core\Database\Schema
{
    /**
     * @var array the abstract column types mapped to physical column types.
     * @since 1.1.6
     */
    public $columnTypes = [
        // no autoincrement, requires sequences and optionally triggers or client input
        'pk' => 'NUMBER(10) NOT NULL PRIMARY KEY',
        // new no sequence identity setting from 12c
        //        'pk' => 'NUMBER GENERATED ALWAYS AS IDENTITY',
    ];

    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'pk':
            case ColumnSchema::TYPE_ID:
                $info['type'] = 'number';
                $info['type_extras'] = '(10)';
                $info['allow_null'] = false;
                $info['auto_increment'] = false;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case ColumnSchema::TYPE_REF:
                $info['type'] = 'number';
                $info['type_extras'] = '(10)';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case ColumnSchema::TYPE_TIMESTAMP_ON_CREATE:
            case ColumnSchema::TYPE_TIMESTAMP_ON_UPDATE:
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $default = 'CURRENT_TIMESTAMP';
                    // ON UPDATE CURRENT_TIMESTAMP not supported by Oracle, use triggers
                    $info['default'] = $default;
                }
                break;

            case ColumnSchema::TYPE_USER_ID:
            case ColumnSchema::TYPE_USER_ID_ON_CREATE:
            case ColumnSchema::TYPE_USER_ID_ON_UPDATE:
                $info['type'] = 'number';
                $info['type_extras'] = '(10)';
                break;

            case ColumnSchema::TYPE_INTEGER:
                $info['type'] = 'number';
                $info['type_extras'] = '(10)';
                break;
            case 'float':
                $info['type'] = 'BINARY_FLOAT';
                break;
            case 'double':
                $info['type'] = 'BINARY_DOUBLE';
                break;
            case 'decimal':
                $info['type'] = 'NUMBER';
                break;
            case 'datetime':
            case 'time':
                $info['type'] = 'TIMESTAMP';
                break;

            case ColumnSchema::TYPE_BOOLEAN:
                $info['type'] = 'number';
                $info['type_extras'] = '(1)';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case ColumnSchema::TYPE_MONEY:
                $info['type'] = 'number';
                $info['type_extras'] = '(19,4)';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    $info['default'] = floatval($default);
                }
                break;

            case ColumnSchema::TYPE_STRING:
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $national =
                    (isset($info['supports_multibyte'])) ? filter_var($info['supports_multibyte'],
                        FILTER_VALIDATE_BOOLEAN) : false;
                if ($fixed) {
                    $info['type'] = ($national) ? 'nchar' : 'char';
                } elseif ($national) {
                    $info['type'] = 'nvarchar2';
                } else {
                    $info['type'] = 'varchar2';
                }
                break;

            case ColumnSchema::TYPE_TEXT:
                $national =
                    (isset($info['supports_multibyte'])) ? filter_var($info['supports_multibyte'],
                        FILTER_VALIDATE_BOOLEAN) : false;
                if ($national) {
                    $info['type'] = 'nclob';
                } else {
                    $info['type'] = 'clob';
                }
                break;

            case ColumnSchema::TYPE_BINARY:
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $info['type'] = ($fixed) ? 'blob' : 'varbinary';
                break;
        }
    }

    protected function validateColumnSettings(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch ($type) {
            // some types need massaging, some need other required properties
            case 'numeric':
            case 'binary_float':
            case 'binary_double':
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
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                }
                break;

            case 'varchar':
            case 'varchar2':
            case 'nvarchar':
            case 'nvarchar2':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
                } else // requires a max length
                {
                    $info['type_extras'] = '(' . static::DEFAULT_STRING_MAX_SIZE . ')';
                }
                break;

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

        $allowNull = (isset($info['allow_null'])) ? filter_var($info['allow_null'], FILTER_VALIDATE_BOOLEAN) : false;
        $definition .= ($allowNull) ? ' NULL' : ' NOT NULL';

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
        return '"' . $name . '"';
    }

    /**
     * Creates a command builder for the database.
     * This method may be overridden by child classes to create a DBMS-specific command builder.
     *
     * @return CommandBuilder command builder instance
     */
    protected function createCommandBuilder()
    {
        return new CommandBuilder($this);
    }

    /**
     * @param boolean $refresh if we need to refresh schema cache.
     *
     * @return string default schema.
     */
    public function getDefaultSchema($refresh = false)
    {
        return strtoupper($this->connection->getUserName());
    }

    /**
     * @param string $table table name with optional schema name prefix, uses default schema name prefix is not
     *                      provided.
     *
     * @return array tuple as ($schemaName,$tableName)
     */
    protected function getSchemaTableName($table)
    {
        $table = strtoupper($table);
        if (count($parts = explode('.', str_replace('"', '', $table))) > 1) {
            return [$parts[0], $parts[1]];
        } else {
            return [$this->getDefaultSchema(), $parts[0]];
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
        $schemaName = $table->schemaName;
        $tableName = $table->tableName;

        $sql = <<<EOD
SELECT a.column_name, a.data_type ||
    case
        when data_precision is not null
            then '(' || a.data_precision ||
                    case when a.data_scale > 0 then ',' || a.data_scale else '' end
                || ')'
        when data_type = 'DATE' then ''
        when data_type = 'NUMBER' then ''
        else '(' || to_char(a.data_length) || ')'
    end as data_type,
    a.nullable, a.data_default,
    (   SELECT D.constraint_type
        FROM ALL_CONS_COLUMNS C
        inner join ALL_constraints D on D.OWNER = C.OWNER and D.constraint_name = C.constraint_name
        WHERE C.OWNER = B.OWNER
           and C.table_name = B.object_name
           and C.column_name = A.column_name
           and D.constraint_type = 'P') as Key,
    com.comments as column_comment
FROM ALL_TAB_COLUMNS A
inner join ALL_OBJECTS B ON b.owner = a.owner and ltrim(B.OBJECT_NAME) = ltrim(A.TABLE_NAME)
LEFT JOIN user_col_comments com ON (A.table_name = com.table_name AND A.column_name = com.column_name)
WHERE
    a.owner = '{$schemaName}'
	and (b.object_type = 'TABLE' or b.object_type = 'VIEW')
	and b.object_name = '{$tableName}'
ORDER by a.column_id
EOD;

        $command = $this->connection->createCommand($sql);

        if (($columns = $command->queryAll()) === []) {
            return false;
        }

        foreach ($columns as $column) {
            $c = $this->createColumn($column);
            $table->addColumn($c);
            if ($c->isPrimaryKey) {
                if ($table->primaryKey === null) {
                    $table->primaryKey = $c->name;
                } elseif (is_string($table->primaryKey)) {
                    $table->primaryKey = [$table->primaryKey, $c->name];
                } else {
                    $table->primaryKey[] = $c->name;
                }

                // set defaults
                $c->autoIncrement = false;
                $table->sequenceName = '';

                $sql = <<<EOD
SELECT trigger_body FROM ALL_TRIGGERS
WHERE table_owner = '{$schemaName}' and table_name = '{$tableName}'
and triggering_event = 'INSERT' and status = 'ENABLED' and trigger_type = 'BEFORE EACH ROW'
EOD;

                $trig = $command = $this->connection->createCommand($sql)->queryScalar();
                if (!empty($trig)) {
                    $c->autoIncrement = true;
                    $seq = stristr($trig, '.nextval', true);
                    $seq = substr($seq, strrpos($seq, ' ') + 1);
                    $table->sequenceName = $seq;
                    if (ColumnSchema::TYPE_INTEGER === $c->type) {
                        $c->type = ColumnSchema::TYPE_ID;
                    }
                }
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
        $c = new ColumnSchema(['name' => $column['COLUMN_NAME']]);
        $c->rawName = $this->quoteColumnName($c->name);
        $c->allowNull = $column['NULLABLE'] === 'Y';
        $c->isPrimaryKey = strpos($column['KEY'], 'P') !== false;
        $c->dbType = $column['DATA_TYPE'];
        $c->extractLimit($column['DATA_TYPE']);
        $c->extractFixedLength($column['DATA_TYPE']);
        $c->extractMultiByteSupport($column['DATA_TYPE']);
        $c->extractType($column['DATA_TYPE']);
        $c->extractDefault($column['DATA_DEFAULT']);
        $c->comment = $column['COLUMN_COMMENT'] === null ? '' : $column['COLUMN_COMMENT'];

        return $c;
    }

    /**
     * Collects the primary and foreign key column details for the given table.
     *
     * @param TableSchema $table the table metadata
     */
    protected function findConstraints($table)
    {
        $sql = <<<EOD
		SELECT D.constraint_type, C.position, D.r_constraint_name,
            C.owner as table_schema,
            C.table_name as table_name,
		    C.column_name as column_name,
            E.owner as referenced_table_schema,
            E.table_name as referenced_table_name,
            F.column_name as referenced_column_name
        FROM ALL_CONS_COLUMNS C
        inner join ALL_constraints D on D.OWNER = C.OWNER and D.constraint_name = C.constraint_name
        left join ALL_constraints E on E.OWNER = D.r_OWNER and E.constraint_name = D.r_constraint_name
        left join ALL_cons_columns F on F.OWNER = E.OWNER and F.constraint_name = E.constraint_name and F.position = C.position
        WHERE D.constraint_type = 'R'
        ORDER BY D.constraint_name, C.position
EOD;
        $constraints = $command = $this->connection->createCommand($sql)->queryAll();

        $this->buildTableRelations($table, $constraints);
    }

    protected function findSchemaNames()
    {
        if ('SYSTEM' == $this->getDefaultSchema()) {
            $sql = 'SELECT username FROM all_users';
        } else {
            $sql = <<<SQL
SELECT username FROM all_users WHERE username not in ('SYSTEM','SYS','SYSAUX')
SQL;
        }

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
        if ($include_views) {
            $condition = "object_type in ('TABLE','VIEW')";
        } else {
            $condition = "object_type = 'TABLE'";
        }

//SELECT table_name, '{$schema}' as table_schema FROM user_tables

        $sql = <<<EOD
SELECT object_name as table_name, owner as table_schema, object_type as table_type FROM all_objects WHERE $condition
EOD;

        if (!empty($schema)) {
            $sql .= " AND owner = '$schema'";
        }

        $defaultSchema = $this->getDefaultSchema();

        $rows = $this->connection->createCommand($sql)->queryAll();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $schemaName = isset($row['TABLE_SCHEMA']) ? $row['TABLE_SCHEMA'] : '';
            $tableName = isset($row['TABLE_NAME']) ? $row['TABLE_NAME'] : '';
            $isView = (0 === strcasecmp('VIEW', $row['TABLE_TYPE']));
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
     * Builds a SQL statement for changing the definition of a column.
     *
     * @param string $table      the table whose column is to be changed. The table name will be properly quoted by the
     *                           method.
     * @param string $column     the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $definition the new column type. The {@link getColumnType} method will be invoked to convert
     *                           abstract column type (if any) into the physical one. Anything that is not recognized
     *                           as abstract type will be kept in the generated SQL. For example, 'string' will be
     *                           turned into 'varchar( 255 )', while 'string not null' will become 'varchar( 255 ) not
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
            ' MODIFY ' .
            $this->quoteColumnName($column) .
            ' ' .
            $this->getColumnType($definition);

        return $sql;
    }

    public function makeConstraintName($prefix, $table, $column)
    {
        $temp = parent::makeConstraintName($prefix, $table, $column);
        // must be less than 30 characters
        if (30 < strlen($temp)) {
            $temp = $prefix . '_' . hash('crc32', $table . '_' . $column);
        }

        return $temp;
    }

    public function requiresCreateIndex($unique = false, $on_create_table = false)
    {
        return !($unique && $on_create_table);
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
     * Resets the sequence value of a table's primary key .
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or max value of a primary key plus one (i.e. sequence trimming).
     *
     * Note, behavior of this method has changed since 1.1.14 release.
     * Please refer to the following issue for more details:
     * {@link  https://github.com/yiisoft/yii/issues/2241}
     *
     * @param TableSchema    $table the table schema whose primary key sequence will be reset
     * @param integer | null $value the value for the primary key of the next new row inserted.
     *                              If this is not set, the next new row's primary key will
     *                              have the max value of a primary key plus one (i.e. sequence trimming).
     *
     * @since 1.1.13
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
                (int)$this->connection
                    ->createCommand("SELECT MAX(\"{$table->primaryKey}\") FROM {$table->rawName}")
                    ->queryScalar();
            $value++;
        }
        $this->connection->createCommand(
            "DROP SEQUENCE \"{
            $table->name}_SEQ\""
        )->execute();
        $this->connection->createCommand(
            "CREATE SEQUENCE \"{
            $table->name}_SEQ\" START WITH {
            $value} INCREMENT BY 1 NOMAXVALUE NOCACHE"
        )->execute();
    }

    /**
     * Enables or disables integrity check.
     *
     * @param boolean $check  whether to turn on or off the integrity check.
     * @param string  $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *
     * @since 1.1.14
     */
    public function checkIntegrity($check = true, $schema = '')
    {
        if ($schema === '') {
            $schema = $this->getDefaultSchema();
        }
        $mode = $check ? 'ENABLE' : 'DISABLE';
        foreach ($this->getTableNames($schema) as $tableInfo) {
            $table = $tableInfo['name'];
            $constraints =
                $this->connection
                    ->createCommand("SELECT CONSTRAINT_NAME FROM USER_CONSTRAINTS WHERE TABLE_NAME=:t AND OWNER=:o")
                    ->queryColumn(
                        [':t' => $table, ':o' => $schema]
                    );
            foreach ($constraints as $constraint) {
                $this->connection
                    ->createCommand("ALTER TABLE \"{$schema}\".\"{$table}\" {$mode} CONSTRAINT \"{$constraint}\"")
                    ->execute();
            }
        }
    }

    /**
     * {@InheritDoc}
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        // ON UPDATE not supported by Oracle
        return parent::addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, null);
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
        $command = $this->connection->createCommand($sql);
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
            $pdoType = $command->getConnection()->getPdoType($rType);
            $command->bindParam(":$pName", $params[$key]['value'], $pdoType | \PDO::PARAM_INPUT_OUTPUT, $rLength);
//                    break;
//            }
        }

        // Oracle stored procedures don't return result sets directly, must use OUT parameter.
        $command->execute();

        return null;
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
        $sql = "SELECT $name($paramStr) FROM DUAL";
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
            (empty($schema) || ($defaultSchema == $schema)) ? 'OBJECT_NAME' : "CONCAT(CONCAT(OWNER,'.'),OBJECT_NAME)";
        $schema = !empty($schema) ? " AND OWNER = '" . $schema . "'" : null;

        $sql = <<<MYSQL
SELECT
    {$select}
FROM
    all_objects
WHERE
    OBJECT_TYPE = :routine_type
    {$schema}
MYSQL;

        return $this->connection->createCommand($sql)->queryColumn([':routine_type' => $type]);
    }

    public function getPrimaryKeyCommands($table, $column)
    {
        // pre 12c versions need sequences and trigger to accomplish autoincrement
        $sequence = strtoupper($table) . '_' . strtoupper($column);
        $trigTable = $this->quoteTableName($table);
        $trigField = $this->quoteColumnName($column);

        $extras = [];
        $extras[] = "CREATE SEQUENCE $sequence";
        $extras[] = <<<SQL
CREATE OR REPLACE TRIGGER {$sequence}
BEFORE INSERT ON {$trigTable}
FOR EACH ROW
BEGIN
  IF :new.{$trigField} IS NOT NULL THEN
    RAISE_APPLICATION_ERROR(-20000, 'ID cannot be specified');
  ELSE
    SELECT {$sequence}.NEXTVAL
    INTO   :new.{$trigField}
    FROM   dual;
  END IF;
END;
SQL;

        return $extras;
    }

    /**
     * @param bool $update
     *
     * @return mixed
     */
    public function getTimestampForSet($update = false)
    {
        return $this->connection->raw('(CURRENT_TIMESTAMP)');
    }
}
