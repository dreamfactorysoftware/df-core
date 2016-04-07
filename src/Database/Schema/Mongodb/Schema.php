<?php
namespace DreamFactory\Core\Database\Schema\Mongodb;

use DreamFactory\Core\Database\ColumnSchema;
use DreamFactory\Core\Database\TableSchema;

/**
 * Schema is the class for retrieving metadata information from a MongoDB database (version 4.1.x and 5.x).
 */
class Schema extends \DreamFactory\Core\Database\Schema\Schema
{
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
        $sql = 'SHOW FULL COLUMNS FROM ' . $table->rawName;
        try {
            $columns = $this->connection->select($sql);
        } catch (\Exception $e) {
            return false;
        }
        foreach ($columns as $column) {
            $column = (array)$column;
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
                    if ((ColumnSchema::TYPE_INTEGER === $c->type)) {
                        $c->type = ColumnSchema::TYPE_ID;
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
                $c->type = ColumnSchema::TYPE_TIMESTAMP_ON_UPDATE;
            } else {
                $c->defaultValue = ['expression' => 'CURRENT_TIMESTAMP'];
                $c->type = ColumnSchema::TYPE_TIMESTAMP_ON_CREATE;
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
        $constraints = [];
        foreach ($this->getSchemaNames() as $schema) {
            $sql = <<<MYSQL
SELECT table_schema, table_name, column_name, referenced_table_schema, referenced_table_name, referenced_column_name
FROM information_schema.KEY_COLUMN_USAGE WHERE referenced_table_name IS NOT NULL AND table_schema = '$schema';
MYSQL;

            $constraints = array_merge($constraints, $this->connection->select($sql));
        }

        $this->buildTableRelations($table, $constraints);
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

        return $this->connection->selectColumn($sql);
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
        $sql = 'SHOW FULL TABLES';

        if (!empty($schema)) {
            $sql .= ' FROM ' . $this->quoteTableName($schema);
        }

        if (!$include_views) {
            $sql .= " WHERE TABLE_TYPE = 'BASE TABLE'";
        }

        $rows = $this->connection->select($sql);

        $defaultSchema = $this->getDefaultSchema();
        $addSchema = (!empty($schema) && ($defaultSchema !== $schema));

        $names = [];
        foreach ($rows as $row) {
            $row = array_change_key_case((array)$row, CASE_UPPER);
            $row = array_values($row);
            $schemaName = $schema;
            $tableName = $row[0];
            $isView = (0 === strcasecmp('VIEW', $row[1]));
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
    ROUTINE_TYPE = 'PROCEDURE'
    {$schema}
MYSQL;

        return $this->connection->selectColumn($sql);
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
        $defaultSchema = $this->getDefaultSchema();
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
    ROUTINE_TYPE = 'FUNCTION'
    {$schema}
MYSQL;

        return $this->connection->selectColumn($sql);
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
        if (null === $row = $db->selectOne('SHOW CREATE TABLE ' . $db->quoteTableName($table))) {
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
     * @return string default schema.
     */
    public function findDefaultSchema()
    {
        $sql = <<<MYSQL
SELECT DATABASE() FROM DUAL
MYSQL;

        return $this->connection->selectValue($sql);
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
}
