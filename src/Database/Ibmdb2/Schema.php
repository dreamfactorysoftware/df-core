<?php
namespace DreamFactory\Core\Database\Ibmdb2;

use DreamFactory\Core\Database\Expression;
use DreamFactory\Core\Database\TableNameSchema;
use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a IBM DB2 database.
 */
class Schema extends \DreamFactory\Core\Database\Schema
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
            $stmt = $this->connection->getPdoInstance()->prepare($sql)->execute();
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
            case 'id':
                $info['type'] = 'integer';
                $info['type_extras'] =
                    'not null PRIMARY KEY GENERATED ALWAYS AS IDENTITY (START WITH 1 INCREMENT BY 1)';
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

            case 'datetime':
                $info['type'] = 'TIMESTAMP';
                break;

            case 'float':
                $info['type'] = 'REAL';
                break;

            case 'double':
                $info['type'] = 'DOUBLE';
                break;

            case 'money':
                $info['type'] = 'decimal';
                $info['type_extras'] = '(19,4)';
                break;

            case 'boolean':
                $info['type'] = 'smallint';
                $info['type_extras'] = '(1)';
                $default = (isset($info['default'])) ? $info['default'] : null;
                if (isset($default)) {
                    // convert to bit 0 or 1, where necessary
                    $info['default'] = (int)filter_var($default, FILTER_VALIDATE_BOOLEAN);
                }
                break;

            case 'string':
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

            case 'text':
                $info['type'] = 'CLOB';
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
     * Loads the metadata for the specified table.
     *
     * @param string $name table name
     *
     * @return TableSchema driver dependent table metadata, null if the table does not exist.
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
        $parts = explode('.', str_replace('"', '', $name));
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

        $command = $this->connection->createCommand($sql);
        $command->bindValue(':table', $table->name);
        $command->bindValue(':schema', $schema);

        if (($columns = $command->queryAll()) === []) {
            return false;
        }

        foreach ($columns as $column) {
            $c = $this->createColumn($column);
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

        $defaultSchema = $this->getDefaultSchema();
        $schema = (!empty($table->schemaName)) ? $table->schemaName : $defaultSchema;

        if ($this->isISeries()) {
            $sql = <<<SQL
SELECT
  parent.table_name AS pktabname,
  parent.column_name AS pkcolname,
  child.column_name AS fkcolname
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
SELECT fk.tabschema AS fktabschema, fk.tabname AS fktabname, fk.colname AS fkcolname,
	pk.tabschema AS pktabschema, pk.tabname AS pktabname, pk.colname AS pkcolname
FROM syscat.references
INNER JOIN syscat.keycoluse AS fk ON fk.constname = syscat.references.constname
INNER JOIN syscat.keycoluse AS pk ON pk.constname = syscat.references.refkeyname AND pk.colseq = fk.colseq
SQL;
        }

        $columns = $columns2 = $this->connection->createCommand($sql)->queryAll();

        foreach ($columns as $key => $column) {
            $ts = $column['FKTABSCHEMA'];
            $tn = $column['FKTABNAME'];
            $cn = $column['FKCOLNAME'];
            $rts = $column['PKTABSCHEMA'];
            $rtn = $column['PKTABNAME'];
            $rcn = $column['PKCOLNAME'];
            if ((0 == strcasecmp($tn, $table->name)) && (0 == strcasecmp($ts, $schema))) {
                $name = ($rts == $defaultSchema) ? $rtn : $rts . '.' . $rtn;

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
                $name = ($ts == $defaultSchema) ? $tn : $ts . '.' . $tn;
                $table->addRelation('has_many', $name, $cn, $rcn);

                // if other has foreign keys to other tables, we can say these are related as well
                foreach ($columns2 as $key2 => $column2) {
                    if (0 != strcasecmp($key, $key2)) // not same key
                    {
                        $ts2 = $column2['FKTABSCHEMA'];
                        $tn2 = $column2['FKTABNAME'];
                        $cn2 = $column2['FKCOLNAME'];
                        if ((0 == strcasecmp($ts2, $ts)) && (0 == strcasecmp($tn2, $tn))
                        ) {
                            $rts2 = $column2['PKTABSCHEMA'];
                            $rtn2 = $column2['PKTABNAME'];
                            $rcn2 = $column2['PKCOLNAME'];
                            if ((0 != strcasecmp($rts2, $schema)) || (0 != strcasecmp($rtn2, $table->name))
                            ) {
                                $name2 = ($rts2 == $defaultSchema) ? $rtn2 : $rts2 . '.' . $rtn2;
                                // not same as parent, i.e. via reference back to self
                                // not the same key
                                $table->addRelation('many_many', $name2, $rcn2, "$name($cn,$cn2)", $rcn);
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

        $command = $this->connection->createCommand($sql);
        $command->bindValue(':table', $table->name);
        $command->bindValue(':schema', $schema);

        $indexes = $command->queryAll();
        foreach ($indexes as $index) {
            $columns = explode("+", ltrim($index['COLNAMES'], '+'));
            foreach ($columns as $colname) {
                if (isset($table->columns[$colname])) {
                    $table->columns[$colname]->isPrimaryKey = true;
                    if (('integer' === $table->columns[$colname]->type) && $table->columns[$colname]->autoIncrement) {
                        $table->columns[$colname]->type = 'id';
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

        $rows = $this->connection->createCommand($sql)->queryColumn();

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

        $defaultSchema = $this->getDefaultSchema();

        $params = (!empty($schema)) ? [':schema' => $schema] : [];
        $rows = $this->connection->createCommand($sql)->queryAll(true, $params);

        $names = [];
        foreach ($rows as $row) {
            $schema = isset($row['TABSCHEMA']) ? $row['TABSCHEMA'] : '';
            $name = isset($row['TABNAME']) ? $row['TABNAME'] : '';
            if ($defaultSchema !== $schema) {
                $name = $schema . '.' . $name;
            }
            $names[strtolower($name)] = new TableNameSchema($name, (0 === strcasecmp('V', $row['TYPE'])));
        }

        return $names;
    }

    /**
     * Creates a command builder for the database.
     * This method overrides parent implementation in order to create a DB2 specific command builder
     *
     * @return CommandBuilder command builder instance
     */
    protected function createCommandBuilder()
    {
        return new CommandBuilder($this);
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
            $table->columns[$table->primaryKey]->autoIncrement
        ) {
            if ($value === null) {
                $value =
                    $this->connection
                        ->createCommand("SELECT MAX({$table->primaryKey}) FROM {$table->rawName}")
                        ->queryScalar() + 1;
            } else {
                $value = (int)$value;
            }

            $this->connection
                ->createCommand("ALTER TABLE {$table->rawName} ALTER COLUMN {$table->primaryKey} RESTART WITH $value")
                ->execute();
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
            $db->createCommand("SET INTEGRITY FOR $tableName ALL IMMEDIATE $enable")->execute();
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

        return $this->connection->createCommand($sql)->queryScalar();
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
        $command = $this->connection->createCommand($sql);
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
                    $pdoType = $command->getConnection()->getPdoType($rType);
                    $command->bindParam(":$pName", $params[$key]['value'], $pdoType | \PDO::PARAM_INPUT_OUTPUT,
                        $rLength);
                    break;
            }
        }

        // support multiple result sets
        $reader = $command->query();
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
//        $sql = "SELECT * from TABLE($name($paramStr))";
        $sql = "SELECT $name($paramStr) FROM SYSIBM.SYSDUMMY1";
        $command = $this->connection->createCommand($sql);

        // do binding
        $command->bindValues($bindings);

        // support multiple result sets
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

        $results = $this->connection->createCommand($sql)->queryAll(true, $params);
        $names = [];
        foreach ($results as $row) {
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
            return new Expression('(CURRENT_TIMESTAMP)');
        } else {
            return new Expression('(GENERATED ALWAYS FOR EACH ROW ON UPDATE AS ROW CHANGE TIMESTAMP)');
        }
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
