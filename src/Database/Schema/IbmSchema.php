<?php
namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Core\Database\DataReader;
use DreamFactory\Core\Database\Schema\Ibmdb2\ColumnSchema;
use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a IBM DB2 database.
 */
class IbmSchema extends Schema
{
    /**
     * @type boolean
     */
    private $isIseries = null;

    private function isISeries()
    {
        if ($this->isIseries !== null) {
            return $this->isIseries;
        }
        try {
            $sql = "SELECT * FROM QSYS2.SYSTABLES";
            $stmt = $this->connection->select($sql);
            $this->isIseries = (bool)$stmt;

            return $this->isIseries;
        } catch (\Exception $ex) {
            $this->isIseries = false;

            return $this->isIseries;
        }
    }

    protected function translateSimpleColumnTypes(array &$info)
    {
        // override this in each schema class
        $type = (isset($info['type'])) ? $info['type'] : null;
        switch (strtolower($type)) {
            // some types need massaging, some need other required properties
            case 'pk':
            case ColumnSchema::TYPE_ID:
                $info['type'] = 'integer';
                $info['type_extras'] =
                    'not null PRIMARY KEY GENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1)';
                $info['allow_null'] = false;
                $info['auto_increment'] = true;
                $info['is_primary_key'] = true;
                break;

            case 'fk':
            case ColumnSchema::TYPE_REF:
                $info['type'] = 'integer';
                $info['is_foreign_key'] = true;
                // check foreign tables
                break;

            case ColumnSchema::TYPE_TIMESTAMP_ON_CREATE:
            case ColumnSchema::TYPE_TIMESTAMP_ON_UPDATE:
                $info['type'] = 'timestamp';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (!isset($default)) {
                    $default = 'CURRENT_TIMESTAMP';
                    if (ColumnSchema::TYPE_TIMESTAMP_ON_UPDATE === $type) {
                        $default .= ' ON UPDATE CURRENT_TIMESTAMP';
                    }
                    $info['default'] = $default;
                }
                break;

            case ColumnSchema::TYPE_USER_ID:
            case ColumnSchema::TYPE_USER_ID_ON_CREATE:
            case ColumnSchema::TYPE_USER_ID_ON_UPDATE:
                $info['type'] = 'integer';
                break;

            case ColumnSchema::TYPE_DATETIME:
                $info['type'] = 'TIMESTAMP';
                break;

            case ColumnSchema::TYPE_FLOAT:
                $info['type'] = 'REAL';
                break;

            case ColumnSchema::TYPE_DOUBLE:
                $info['type'] = 'DOUBLE';
                break;

            case ColumnSchema::TYPE_MONEY:
                $info['type'] = 'decimal';
                $info['type_extras'] = '(19,4)';
                break;

            case ColumnSchema::TYPE_BOOLEAN:
                $info['type'] = 'smallint';
                $info['type_extras'] = '(1)';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case ColumnSchema::TYPE_STRING:
                $fixed =
                    (isset($info['fixed_length'])) ? filter_var($info['fixed_length'], FILTER_VALIDATE_BOOLEAN) : false;
                $national =
                    (isset($info['supports_multibyte'])) ? filter_var($info['supports_multibyte'],
                        FILTER_VALIDATE_BOOLEAN) : false;
                if ($fixed) {
                    $info['type'] = ($national) ? 'graphic' : 'character';
                } elseif ($national) {
                    $info['type'] = 'vargraphic';
                } else {
                    $info['type'] = 'varchar';
                }
                break;

            case ColumnSchema::TYPE_TEXT:
                $info['type'] = 'CLOB';
                break;

            case ColumnSchema::TYPE_BINARY:
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
        switch (strtolower($type)) {
            // some types need massaging, some need other required properties
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
            case 'real':
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

            case 'character':
            case 'graphic':
            case 'binary':
            case 'varchar':
            case 'vargraphic':
            case 'varbinary':
            case 'clob':
            case 'dbclob':
            case 'blob':
                $length = (isset($info['length'])) ? $info['length'] : ((isset($info['size'])) ? $info['size'] : null);
                if (isset($length)) {
                    $info['type_extras'] = "($length)";
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
     */
    public function quoteSimpleTableName($name)
    {
        return $name;
    }

    /**
     * Quotes a column name for use in a query.
     * A simple column name does not contain prefix.
     *
     * @param string $name column name
     *
     * @return string the properly quoted column name
     */
    public function quoteSimpleColumnName($name)
    {
        return $name;
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
        $schema = (!empty($table->schemaName)) ? $table->schemaName : $this->getDefaultSchema();

        if ($this->isISeries()) {
            $sql = <<<SQL
SELECT column_name AS colname,
       ordinal_position AS colno,
       data_type AS typename,
       CAST(column_default AS VARCHAR(254)) AS default,
       is_nullable AS nulls,
       length AS length,
       numeric_scale AS scale,
       is_identity AS identity
FROM qsys2.syscolumns
WHERE table_name = :table AND table_schema = :schema
ORDER BY ordinal_position
SQL;
        } else {
            $sql = <<<SQL
SELECT colname AS colname,
       colno,
       typename,
       CAST(default AS VARCHAR(254)) AS default,
       nulls,
       length,
       scale,
       identity
FROM syscat.columns
WHERE syscat.columns.tabname = :table AND syscat.columns.tabschema = :schema
ORDER BY colno
SQL;
        }

        $columns = $this->connection->select($sql, [':table' => $table->tableName, ':schema' => $schema]);

        if (empty($columns)) {
            return false;
        }

        foreach ($columns as $column) {
            $c = $this->createColumn(array_change_key_case((array)$column, CASE_UPPER));
            $table->addColumn($c);
        }

        return (count($table->columns) > 0);
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
        $c = new ColumnSchema(['name' => $column['COLNAME']]);
        $c->rawName = $this->quoteColumnName($c->name);
        $c->allowNull = ($column['NULLS'] == 'Y');
        $c->autoIncrement = ($column['IDENTITY'] == 'Y');
        $c->dbType = $column['TYPENAME'];

        if (preg_match('/(varchar|character|clob|graphic|binary|blob)/i', $column['TYPENAME'])) {
            $c->size = $c->precision = $column['LENGTH'];
        } elseif (preg_match('/(decimal|double|real)/i', $column['TYPENAME'])) {
            $c->size = $c->precision = $column['LENGTH'];
            $c->scale = $column['SCALE'];
        }

        $c->extractFixedLength($column['TYPENAME']);
        $c->extractMultiByteSupport($column['TYPENAME']);
        $c->extractType($column['TYPENAME']);
        if (is_string($column['DEFAULT'])) {
            $column['DEFAULT'] = trim($column['DEFAULT'], '\'');
        }
        $default = ($column['DEFAULT'] == "NULL") ? null : $column['DEFAULT'];

        $c->extractDefault($default);

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

        if ($this->isISeries()) {
            $sql = <<<SQL
SELECT
  parent.table_schema AS referenced_table_schema,
  parent.table_name AS referenced_table_name,
  parent.column_name AS referenced_column_name,
  child.table_schema AS table_schema
  child.table_name AS table_name
  child.column_name AS column_name
FROM qsys2.syskeycst child
INNER JOIN qsys2.sysrefcst crossref
    ON child.constraint_schema = crossref.constraint_schema
   AND child.constraint_name = crossref.constraint_name
INNER JOIN qsys2.syskeycst parent
    ON crossref.unique_constraint_schema = parent.constraint_schema
   AND crossref.unique_constraint_name = parent.constraint_name
INNER JOIN qsys2.syscst coninfo
    ON child.constraint_name = coninfo.constraint_name
WHERE child.table_name = :table AND child.table_schema = :schema
  AND coninfo.constraint_type = 'FOREIGN KEY'
SQL;
        } else {
            $sql = <<<SQL
SELECT fk.tabschema AS table_schema, fk.tabname AS table_name, fk.colname AS column_name,
	pk.tabschema AS referenced_table_schema, pk.tabname AS referenced_table_name, pk.colname AS referenced_column_name
FROM syscat.references
INNER JOIN syscat.keycoluse AS fk ON fk.constname = syscat.references.constname
INNER JOIN syscat.keycoluse AS pk ON pk.constname = syscat.references.refkeyname AND pk.colseq = fk.colseq
SQL;
        }

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
        $schema = (!empty($table->schemaName)) ? $table->schemaName : $this->getDefaultSchema();

        if ($this->isISeries()) {
            $sql = <<<SQL
SELECT column_name As colnames
FROM qsys2.syscst 
INNER JOIN qsys2.syskeycst
  ON qsys2.syscst.constraint_name = qsys2.syskeycst.constraint_name
 AND qsys2.syscst.table_schema = qsys2.syskeycst.table_schema 
 AND qsys2.syscst.table_name = qsys2.syskeycst.table_name
WHERE qsys2.syscst.constraint_type = 'PRIMARY KEY'
  AND qsys2.syscst.table_name = :table AND qsys2.syscst.table_schema = :schema
SQL;
        } else {
            $sql = <<<SQL
SELECT colnames AS colnames
FROM syscat.indexes
WHERE uniquerule = 'P'
  AND tabname = :table AND tabschema = :schema
SQL;
        }

        $indexes = $this->connection->select($sql, [':table' => $table->tableName, ':schema' => $schema]);
        foreach ($indexes as $index) {
            $index = array_change_key_case((array)$index, CASE_UPPER);
            $columns = explode("+", ltrim($index['COLNAMES'], '+'));
            foreach ($columns as $colname) {
                $cnk = strtolower($colname);
                if (isset($table->columns[$cnk])) {
                    $table->columns[$cnk]->isPrimaryKey = true;
                    if ((ColumnSchema::TYPE_INTEGER === $table->columns[$cnk]->type) &&
                        $table->columns[$cnk]->autoIncrement
                    ) {
                        $table->columns[$cnk]->type = ColumnSchema::TYPE_ID;
                    }
                    if ($table->primaryKey === null) {
                        $table->primaryKey = $colname;
                    } elseif (is_string($table->primaryKey)) {
                        $table->primaryKey = [$table->primaryKey, $colname];
                    } else {
                        $table->primaryKey[] = $colname;
                    }
                }
            }
        }

        /* @var $c ColumnSchema */
        foreach ($table->columns as $c) {
            if ($c->autoIncrement && $c->isPrimaryKey) {
                $table->sequenceName = $c->rawName;
                break;
            }
        }
    }

    protected function findSchemaNames()
    {
        if ($this->isISeries()) {
            $sql = <<<SQL
SELECT SCHEMA as SCHEMANAME FROM QSYS2.SYSTABLES WHERE SYSTEM_TABLE = 'N' ORDER BY SCHEMANAME;
SQL;
        } else {
            $sql = <<<SQL
SELECT SCHEMANAME FROM SYSCAT.SCHEMATA WHERE DEFINERTYPE != 'S' ORDER BY SCHEMANAME;
SQL;
        }

        $rows = $this->selectColumn($sql);

        $defaultSchema = $this->getDefaultSchema();
        if (!empty($defaultSchema) && (false === array_search($defaultSchema, $rows))) {
            $rows[] = $defaultSchema;
        }

        return $rows;
    }

    /**
     * Returns all table names in the database.
     *
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *                       If not empty, the returned table names will be prefixed with the schema name.
     *
     * @return array all table names in the database.
     */
    protected function findTableNames($schema = '', $include_views = true)
    {
        if ($include_views) {
            $condition = "('T','V')";
        } else {
            $condition = "('T')";
        }

        if ($this->isISeries()) {
            $sql = <<<SQL
SELECT TABLE_SCHEMA as TABSCHEMA, TABLE_NAME as TABNAME, TABLE_TYPE AS TYPE
FROM QSYS2.SYSTABLES
WHERE TABLE_TYPE IN $condition AND SYSTEM_TABLE = 'N'
SQL;
            if ($schema !== '') {
                $sql .= <<<SQL
  AND TABLE_SCHEMA = :schema
SQL;
            }
        } else {
            $sql = <<<SQL
SELECT TABSCHEMA, TABNAME, TYPE
FROM SYSCAT.TABLES
WHERE TYPE IN $condition AND OWNERTYPE != 'S'
SQL;
            if (!empty($schema)) {
                $sql .= <<<SQL
  AND TABSCHEMA=:schema
SQL;
            }
        }
        $sql .= <<<SQL
  ORDER BY TABNAME;
SQL;

        $params = (!empty($schema)) ? [':schema' => $schema] : [];
        $rows = $this->connection->select($sql, $params);

        $defaultSchema = $this->getDefaultSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $schemaName = isset($row['TABSCHEMA']) ? $row['TABSCHEMA'] : '';
            $tableName = isset($row['TABNAME']) ? $row['TABNAME'] : '';
            $isView = (0 === strcasecmp('V', $row['TYPE']));
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
     * Resets the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or 1.
     *
     * @param TableSchema $table    the table schema whose primary key sequence will be reset
     * @param mixed       $value    the value for the primary key of the next new row inserted. If this is not set,
     *                              the next new row's primary key will have a value 1.
     */
    public function resetSequence($table, $value = null)
    {
        if ($table->sequenceName !== null &&
            is_string($table->primaryKey) &&
            $table->columns[strtolower($table->primaryKey)]->autoIncrement
        ) {
            if ($value === null) {
                $value = $this->selectValue("SELECT MAX({$table->primaryKey}) FROM {$table->rawName}") + 1;
            } else {
                $value = (int)$value;
            }

            $this->connection
                ->statement("ALTER TABLE {$table->rawName} ALTER COLUMN {$table->primaryKey} RESTART WITH $value");
        }
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
        $enable = $check ? 'CHECKED' : 'UNCHECKED';
        $tableNames = $this->getTableNames($schema);
        $db = $this->connection;
        foreach ($tableNames as $tableInfo) {
            $tableName = $tableInfo['name'];
            $db->statement("SET INTEGRITY FOR $tableName ALL IMMEDIATE $enable");
        }
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
        return "TRUNCATE TABLE " . $this->quoteTableName($table) . " IMMEDIATE ";
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
        $tableSchema = $this->getTable($table);
        $columnSchema = $tableSchema->getColumn(rtrim($column));

        $allowNullNewType = !preg_match("/not +null/i", $definition);

        $definition = preg_replace("/ +(not)? *null/i", "", $definition);

        $sql =
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' ALTER COLUMN ' .
            $this->quoteColumnName($column) .
            ' ' .
            ' SET DATA TYPE ' .
            $this->getColumnType($definition);

        if ($columnSchema->allowNull != $allowNullNewType) {
            if ($allowNullNewType) {
                $sql .= ' ALTER COLUMN ' . $this->quoteColumnName($column) . 'DROP NOT NULL';
            } else {
                $sql .= ' ALTER COLUMN ' . $this->quoteColumnName($column) . 'SET NOT NULL';
            }
        }

        return $sql;
    }

    /**
     * @return string default schema.
     */
    public function findDefaultSchema()
    {
        $sql = <<<SQL
VALUES CURRENT_SCHEMA
SQL;

        return $this->selectValue($sql);
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

        $params = [];
        switch (trim(strtoupper($type))) {
            case 'PROCEDURE':
                $params[':type'] = 'P';
                break;
            case 'FUNCTION':
                $params[':type'] = 'F';
                break;
            default:
                throw new \InvalidArgumentException('The type "' . $type . '" is invalid.');
        }

        if (!empty($schema)) {
            $where = ' AND ROUTINESCHEMA = :schema';
            $params[':schema'] = $schema;
        }

        $sql = <<<SQL
SELECT
    ROUTINESCHEMA,ROUTINENAME
FROM
    SYSCAT.ROUTINES
WHERE
    OWNERTYPE != 'S' AND ROUTINETYPE = :type
    {$where}
SQL;

        $results = $this->connection->select($sql, $params);
        $names = [];
        foreach ($results as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $rs = isset($row['ROUTINESCHEMA']) ? $row['ROUTINESCHEMA'] : '';
            $rn = isset($row['ROUTINENAME']) ? $row['ROUTINENAME'] : '';
            $names[] = ($defaultSchema == $rs) ? $rn : $rs . '.' . $rn;
        }

        return $names;
    }

    /**
     * @param bool $update
     *
     * @return mixed
     */
    public function getTimestampForSet($update = false)
    {
        if (!$update) {
            return $this->connection->raw('(CURRENT_TIMESTAMP)');
        } else {
            return $this->connection->raw('(GENERATED ALWAYS FOR EACH ROW ON UPDATE AS ROW CHANGE TIMESTAMP)');
        }
    }

    public function parseValueForSet($value, $field_info)
    {
        switch ($field_info->type) {
            case ColumnSchema::TYPE_BOOLEAN:
                $value = (filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
                break;
        }

        return $value;
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
        $statement = $this->connection->getPdo()->prepare($sql);
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
//        $sql = "SELECT * from TABLE($name($paramStr))";
        $sql = "SELECT $name($paramStr) FROM SYSIBM.SYSDUMMY1";
        /** @type \PDOStatement $statement */
        $statement = $this->connection->getPdo()->prepare($sql);

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
}
