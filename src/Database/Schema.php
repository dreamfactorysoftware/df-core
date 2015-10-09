<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Library\Utility\Scalar;
use League\Flysystem\NotSupportedException;

/**
 * Schema is the base class for retrieving metadata information.
 *
 * @property Connection     $connection     Database connection. The connection is active.
 * @property array          $tables         The metadata for all tables in the database.
 * Each array element is an instance of {@link TableSchema} (or its child class).
 * The array keys are table names.
 * @property array          $tableNames     All table names in the database.
 * @property CommandBuilder $commandBuilder The SQL command builder for this connection.
 */
abstract class Schema
{
    const DEFAULT_STRING_MAX_SIZE = 255;

    /**
     * @type string
     */
    protected $defaultSchema;
    /**
     * @var array
     */
    protected $schemaNames = [];
    /**
     * @var array
     */
    protected $tableNames = [];
    /**
     * @var array
     */
    protected $tables = [];
    /**
     * @var array
     */
    protected $procedureNames = [];
    /**
     * @var array
     */
    protected $procedures = [];
    /**
     * @var array
     */
    protected $functionNames = [];
    /**
     * @var array
     */
    protected $functions = [];
    /**
     * @var Connection
     */
    protected $connection;
    /**
     * @var
     */
    protected $builder;

    /**
     * Loads the metadata for the specified table.
     *
     * @param string $name table name
     *
     * @return TableSchema driver dependent table metadata, null if the table does not exist.
     */
    abstract protected function loadTable($name);

    /**
     * Constructor.
     *
     * @param Connection $conn database connection.
     */
    public function __construct($conn)
    {
        $this->connection = $conn;
    }

    /**
     * @return Connection database connection. The connection is active.
     */
    public function getDbConnection()
    {
        return $this->connection;
    }

    /**
     * Returns the default schema name for the connection.
     *
     * @param boolean $refresh if we need to refresh schema cache.
     *
     * @return string default schema.
     */
    public function getDefaultSchema($refresh = false)
    {
        if (!$refresh) {
            if (!empty($this->defaultSchema)) {
                return $this->defaultSchema;
            } elseif (null !== $this->defaultSchema = $this->connection->getFromCache('default_schema')) {
                return $this->defaultSchema;
            }
        }

        $this->defaultSchema = $this->findDefaultSchema();
        $this->connection->addToCache('default_schema', $this->defaultSchema, true);

        return $this->defaultSchema;
    }

    /**
     * Returns the default schema name for the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply returns null.
     *
     * @throws \Exception
     * @return array all schema names in the database.
     */
    protected function findDefaultSchema()
    {
        return null;
    }

    /**
     * Returns all schema names on the connection.
     *
     * @param boolean $refresh if we need to refresh schema cache.
     *
     * @return array all schema names on the connection.
     */
    public function getSchemaNames($refresh = false)
    {
        if ($this->connection->isDefaultSchemaOnly()) {
            return [$this->getDefaultSchema()];
        }
        if (!$refresh) {
            if (!empty($this->schemaNames)) {
                return $this->schemaNames;
            } elseif (null !== $this->schemaNames = $this->connection->getFromCache('schema_names')) {
                return $this->schemaNames;
            }
        }

        $this->schemaNames = $this->findSchemaNames();
        natcasesort($this->schemaNames);

        $this->connection->addToCache('schema_names', $this->schemaNames, true);

        return $this->schemaNames;
    }

    /**
     * Returns all schema names in the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     *
     * @throws \Exception
     * @return array all schema names in the database.
     */
    protected function findSchemaNames()
    {
//        throw new \Exception( "{get_class( $this )} does not support fetching all schema names." );
        return [''];
    }

    /**
     * @param string $name       The name of the table to check
     * @param bool   $returnName If true, the table name is returned instead of TRUE
     *
     * @throws \InvalidArgumentException
     * @return bool
     */
    public function doesTableExist($name, $returnName = false)
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Table name cannot be empty.');
        }

        //  Build the lower-cased table array
        $tables = $this->getTableNames();

        //	Search normal, return real name
        if (false !== array_key_exists(strtolower($name), $tables)) {
            return $returnName ? $tables[strtolower($name)]->name : true;
        }

        return false;
    }

    /**
     * Obtains the metadata for the named table.
     *
     * @param string  $name    table name
     * @param boolean $refresh if we need to refresh schema cache for a table.
     *                         Parameter available since 1.1.9
     *
     * @return TableSchema table metadata. Null if the named table does not exist.
     */
    public function getTable($name, $refresh = false)
    {
        if (!$refresh) {
            if (isset($this->tables[$name])) {
                return $this->tables[$name];
            } elseif (null !== $table = $this->connection->getFromCache('table:' . $name)) {
                $this->tables[$name] = $table;

                return $this->tables[$name];
            }
        }

        if ($this->connection->tablePrefix !== null && strpos($name, '{{') !== false) {
            $realName = preg_replace('/\{\{(.*?)\}\}/', $this->connection->tablePrefix . '$1', $name);
        } else {
            $realName = $name;
        }

        if (null === $table = $this->loadTable($realName)) {
            return null;
        }

        // merge db extras
        if (!empty($extras = $this->connection->getSchemaExtrasForTables($name, false))) {
            $extras = (isset($extras[0])) ? $extras[0] : null;
            $table->fill($extras);
        }
        if (!empty($extras = $this->connection->getSchemaExtrasForFields($name, '*'))) {
            foreach ($extras as $extra) {
                if (!empty($columnName = (isset($extra['field'])) ? $extra['field'] : null)) {
                    if (null !== $column = $table->getColumn($columnName)) {
                        $column->fill($extra);
                    }
                }
            }
        }

        $this->tables[$name] = $table;
        $this->connection->addToCache('table:' . $name, $table, true);

        return $table;
    }

    /**
     * Returns the metadata for all tables in the database.
     *
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @param bool   $include_views
     *
     * @param bool   $refresh
     *
     * @return array the metadata for all tables in the database.
     * Each array element is an instance of {@link TableSchema} (or its child class).
     * The array keys are table names.
     */
    public function getTables($schema = '', $include_views = true, $refresh = false)
    {
        $tables = [];
        foreach ($this->getTableNames($schema, $include_views, $refresh) as $name) {
            if (($table = $this->getTable($name, $refresh)) !== null) {
                $tables[$name] = $table;
            }
        }

        return $tables;
    }

    /**
     * Returns all table names in the database.
     *
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *                       If not empty, the returned table names will be prefixed with the schema name.
     * @param bool   $include_views
     * @param bool   $refresh
     *
     * @return TableNameSchema[] all table names in the database.
     */
    public function getTableNames($schema = '', $include_views = true, $refresh = false)
    {
        if ($refresh) {
            // go ahead and reset all schemas
            $this->getCachedTableNames($include_views, $refresh);
        }
        if (empty($schema)) {
            $names = [];
            foreach ($this->getSchemaNames() as $schema) {
                if (!isset($this->tableNames[$schema])) {
                    $this->getCachedTableNames($include_views);
                }

                $temp = (isset($this->tableNames[$schema]) ? $this->tableNames[$schema] : []);
                $names = array_merge($names, $temp);
            }
        } else {
            if (!isset($this->tableNames[$schema])) {
                $this->getCachedTableNames($include_views);
            }

            $names = (isset($this->tableNames[$schema]) ? $this->tableNames[$schema] : []);
        }

        return $names;
    }

    /**
     * @param bool $include_views
     * @param bool $refresh
     *
     * @throws \Exception
     */
    protected function getCachedTableNames($include_views = true, $refresh = false)
    {
        if ($refresh ||
            (empty($this->tableNames) &&
                (null === $this->tableNames = $this->connection->getFromCache('table_names')))
        ) {
            $names = [];
            foreach ($this->getSchemaNames($refresh) as $temp) {
                /** @type TableNameSchema[] $tables */
                $tables = $this->findTableNames($temp, $include_views);
                ksort($tables, SORT_NATURAL); // sort alphabetically
                // merge db extras
                if (!empty($extrasEntries = $this->connection->getSchemaExtrasForTables(array_keys($tables), false))) {
                    foreach ($extrasEntries as $extras) {
                        if (!empty($extraName = strtolower(strval($extras['table'])))) {
                            if (array_key_exists($extraName, $tables)) {
                                $tables[$extraName]->fill($extras);
                            }
                        }
                    }
                }
                $names[$temp] = $tables;
            }
            $this->tableNames = $names;
            $this->connection->addToCache('table_names', $this->tableNames, true);
        }
    }

    /**
     * Returns all table names in the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     *
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     *                       If not empty, the returned table names will be prefixed with the schema name.
     * @param bool   $include_views
     *
     * @throws \Exception if current schema does not support fetching all table names
     * @return array all table names in the database.
     */
    protected function findTableNames($schema = '', $include_views = true)
    {
        throw new NotImplementedException("Database or driver does not support fetching all table names.");
    }

    /**
     * Obtains the metadata for the named stored procedure.
     *
     * @param string  $name    stored procedure name
     * @param boolean $refresh if we need to refresh schema cache for a stored procedure.
     *                         Parameter available since 1.1.9
     *
     * @return ProcedureSchema stored procedure metadata. Null if the named stored procedure does not exist.
     */
    public function getProcedure($name, $refresh = false)
    {
        if ($refresh === false && isset($this->procedures[$name])) {
            return $this->procedures[$name];
        } else {
            $realName = $name;

            $this->procedures[$name] = $procedure = $this->loadProcedure($realName);

            return $procedure;
        }
    }

    /**
     * Loads the metadata for the specified stored procedure.
     *
     * @param string $name procedure name
     *
     * @throws \Exception
     * @return ProcedureSchema driver dependent procedure metadata, null if the procedure does not exist.
     */
    protected function loadProcedure($name)
    {
        throw new NotImplementedException("Database or driver does not support loading stored procedure.");
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
        throw new NotImplementedException("Database or driver does not support calling stored procedures.");
    }

    /**
     * Returns the metadata for all stored procedures in the database.
     *
     * @param string $schema the schema of the procedures. Defaults to empty string, meaning the current or default
     *                       schema.
     *
     * @return array the metadata for all stored procedures in the database.
     * Each array element is an instance of {@link ProcedureSchema} (or its child class).
     * The array keys are procedure names.
     */
    public function getProcedures($schema = '')
    {
        $procedures = [];
        foreach ($this->getProcedureNames($schema) as $name) {
            if (($procedure = $this->getProcedure($name)) !== null) {
                $procedures[$name] = $procedure;
            }
        }

        return $procedures;
    }

    /**
     * Returns all stored procedure names in the database.
     *
     * @param string $schema the schema of the procedures. Defaults to empty string, meaning the current or default
     *                       schema. If not empty, the returned procedure names will be prefixed with the schema name.
     *
     * @param bool   $refresh
     *
     * @return array all procedure names in the database.
     */
    public function getProcedureNames($schema = '', $refresh = false)
    {
        if ($refresh) {
            // go ahead and reset all schemas
            $this->getCachedProcedureNames($refresh);
        }
        if (empty($schema)) {
            $names = [];
            foreach ($this->getSchemaNames() as $schema) {
                if (!isset($this->procedureNames[$schema])) {
                    $this->getCachedProcedureNames();
                }

                $temp = (isset($this->procedureNames[$schema]) ? $this->procedureNames[$schema] : []);
                $names = array_merge($names, $temp);
            }

            return array_values($names);
        } else {
            if (!isset($this->procedureNames[$schema])) {
                $this->getCachedProcedureNames();
            }

            $names = (isset($this->procedureNames[$schema]) ? $this->procedureNames[$schema] : []);

            return array_values($names);
        }
    }

    /**
     * @param bool $refresh
     *
     * @throws \Exception
     */
    protected function getCachedProcedureNames($refresh = false)
    {
        if ($refresh ||
            (empty($this->procedureNames) &&
                (null === $this->procedureNames = $this->connection->getFromCache('proc_names')))
        ) {
            $names = [];
            foreach ($this->getSchemaNames($refresh) as $temp) {
                $procs = $this->findProcedureNames($temp);
                natcasesort($procs);
                $names[$temp] = $procs;
            }
            $this->procedureNames = $names;
            $this->connection->addToCache('proc_names', $this->procedureNames, true);
        }
    }

    /**
     * Returns all stored procedure names in the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     *
     * @param string $schema the schema of the stored procedure. Defaults to empty string, meaning the current or
     *                       default schema. If not empty, the returned stored procedure names will be prefixed with
     *                       the schema name.
     *
     * @throws \Exception if current schema does not support fetching all stored procedure names
     * @return array all stored procedure names in the database.
     */
    protected function findProcedureNames($schema = '')
    {
        throw new NotImplementedException("Database or driver does not support fetching all stored procedure names.");
    }

    /**
     * Obtains the metadata for the named stored function.
     *
     * @param string  $name    stored function name
     * @param boolean $refresh if we need to refresh schema cache for a stored function.
     *
     * @return FunctionSchema stored function metadata. Null if the named stored function does not exist.
     */
    public function getFunction($name, $refresh = false)
    {
        if ($refresh === false && isset($this->functions[$name])) {
            return $this->functions[$name];
        } else {
            $realName = $name;
            $this->functions[$name] = $function = $this->loadFunction($realName);

            return $function;
        }
    }

    /**
     * Returns the metadata for all stored functions in the database.
     *
     * @param string $schema the schema of the functions. Defaults to empty string, meaning the current or default
     *                       schema.
     *
     * @return array the metadata for all stored functions in the database.
     * Each array element is an instance of {@link FunctionSchema} (or its child class).
     * The array keys are functions names.
     */
    public function getFunctions($schema = '')
    {
        $functions = [];
        foreach ($this->getFunctionNames($schema) as $name) {
            if (($procedure = $this->getFunction($name)) !== null) {
                $functions[$name] = $procedure;
            }
        }

        return $functions;
    }

    /**
     * Returns all stored functions names in the database.
     *
     * @param string $schema the schema of the functions. Defaults to empty string, meaning the current or default
     *                       schema. If not empty, the returned functions names will be prefixed with the schema name.
     *
     * @param bool   $refresh
     *
     * @return array all stored functions names in the database.
     */
    public function getFunctionNames($schema = '', $refresh = false)
    {
        if ($refresh) {
            // go ahead and reset all schemas
            $this->getCachedFunctionNames($refresh);
        }
        if (empty($schema)) {
            $names = [];
            foreach ($this->getSchemaNames() as $schema) {
                if (!isset($this->functionNames[$schema])) {
                    $this->getCachedFunctionNames();
                }

                $temp = (isset($this->functionNames[$schema]) ? $this->functionNames[$schema] : []);
                $names = array_merge($names, $temp);
            }

            return array_values($names);
        } else {
            if (!isset($this->functionNames[$schema])) {
                $this->getCachedFunctionNames();
            }

            $names = (isset($this->functionNames[$schema]) ? $this->functionNames[$schema] : []);

            return array_values($names);
        }
    }

    /**
     * @param bool $refresh
     *
     * @throws \Exception
     */
    protected function getCachedFunctionNames($refresh = false)
    {
        if ($refresh ||
            (empty($this->functionNames) &&
                (null === $this->functionNames = $this->connection->getFromCache('func_names')))
        ) {
            $names = [];
            foreach ($this->getSchemaNames($refresh) as $temp) {
                $funcs = $this->findFunctionNames($temp);
                natcasesort($funcs);
                $names[$temp] = $funcs;
            }
            $this->functionNames = $names;
            $this->connection->addToCache('func_names', $this->functionNames, true);
        }
    }

    /**
     * Returns all stored function names in the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     *
     * @param string $schema the schema of the stored function. Defaults to empty string, meaning the current or
     *                       default schema. If not empty, the returned stored function names will be prefixed with the
     *                       schema name.
     *
     * @throws \Exception if current schema does not support fetching all stored function names
     * @return array all stored function names in the database.
     */
    protected function findFunctionNames($schema = '')
    {
        throw new NotImplementedException("Database or driver does not support fetching all stored function names.");
    }

    /**
     * Loads the metadata for the specified function.
     *
     * @param string $name function name
     *
     * @throws \Exception
     * @return FunctionSchema driver dependent function metadata, null if the function does not exist.
     */
    protected function loadFunction($name)
    {
        throw new NotImplementedException("Database or driver does not support loading stored functions.");
    }

    /**
     * @param string $name
     * @param array  $params
     *
     * @return mixed
     * @throws \Exception
     */
    public function callFunction($name, &$params)
    {
        throw new NotImplementedException("Database or driver does not support calling stored functions.");
    }

    /**
     * @return CommandBuilder the SQL command builder for this connection.
     */
    public function getCommandBuilder()
    {
        if ($this->builder !== null) {
            return $this->builder;
        } else {
            return $this->builder = $this->createCommandBuilder();
        }
    }

    /**
     * Refreshes the schema.
     * This method resets the loaded table metadata and command builder
     * so that they can be recreated to reflect the change of schema.
     */
    public function refresh()
    {
        $this->tables = [];
        $this->tableNames = [];
        $this->procedures = [];
        $this->procedureNames = [];
        $this->functions = [];
        $this->functionNames = [];
        $this->schemaNames = [];
        $this->builder = null;

        $this->connection->flushCache();
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     *
     * @param string $name table name
     *
     * @return string the properly quoted table name
     * @see quoteSimpleTableName
     */
    public function quoteTableName($name)
    {
        if (strpos($name, '.') === false) {
            return $this->quoteSimpleTableName($name);
        }
        $parts = explode('.', $name);
        foreach ($parts as $i => $part) {
            $parts[$i] = $this->quoteSimpleTableName($part);
        }

        return implode('.', $parts);
    }

    /**
     * Quotes a simple table name for use in a query.
     * A simple table name does not schema prefix.
     *
     * @param string $name table name
     *
     * @return string the properly quoted table name
     * @since 1.1.6
     */
    public function quoteSimpleTableName($name)
    {
        return "'" . $name . "'";
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     *
     * @param string $name column name
     *
     * @return string the properly quoted column name
     * @see quoteSimpleColumnName
     */
    public function quoteColumnName($name)
    {
        if (($pos = strrpos($name, '.')) !== false) {
            $prefix = $this->quoteTableName(substr($name, 0, $pos)) . '.';
            $name = substr($name, $pos + 1);
        } else {
            $prefix = '';
        }

        return $prefix . ($name === '*' ? $name : $this->quoteSimpleColumnName($name));
    }

    /**
     * Quotes a simple column name for use in a query.
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
        $name1 = str_replace(['"', '`', "'"], '', $name1);
        $name2 = str_replace(['"', '`', "'"], '', $name2);
        if (($pos = strrpos($name1, '.')) !== false) {
            $name1 = substr($name1, $pos + 1);
        }
        if (($pos = strrpos($name2, '.')) !== false) {
            $name2 = substr($name2, $pos + 1);
        }
        if ($this->connection->tablePrefix !== null) {
            if (strpos($name1, '{') !== false) {
                $name1 = $this->connection->tablePrefix . str_replace(['{', '}'], '', $name1);
            }
            if (strpos($name2, '{') !== false) {
                $name2 = $this->connection->tablePrefix . str_replace(['{', '}'], '', $name2);
            }
        }

        return $name1 === $name2;
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
     * @param string           $table_name
     * @param array            $fields
     * @param null|TableSchema $oldSchema
     * @param bool             $allow_update
     * @param bool             $allow_delete
     *
     * @throws \Exception
     * @return string
     */
    public function buildTableFields(
        $table_name,
        $fields,
        $oldSchema = null,
        $allow_update = false,
        $allow_delete = false
    ){
        if (!is_array($fields) || empty($fields)) {
            throw new \Exception('There are no fields in the requested schema.');
        }

        if (!isset($fields[0])) {
            // single record possibly passed in without wrapper array
            $fields = [$fields];
        }

        $columns = [];
        $alterColumns = [];
        $references = [];
        $indexes = [];
        $labels = [];
        $dropColumns = [];
        $extraCommands = [];
        $newFields = [];
        foreach ($fields as $field) {
            $newFields[$field['name']] = array_change_key_case($field, CASE_LOWER);
        }

        if ($allow_delete && isset($oldSchema, $oldSchema->columns)) {
            // check for columns to drop
            foreach ($oldSchema->columns as $oldName => $oldField) {
                if (!isset($newFields[$oldName])) {
                    $dropColumns[] = $oldName;
                }
            }
        }

        foreach ($newFields as $name => $field) {
            if (empty($name)) {
                throw new \Exception("Invalid schema detected - no name element.");
            }

            /** @type ColumnSchema $oldField */
            $oldField = isset($oldSchema) ? $oldSchema->getColumn($name) : null;
            $isAlter = (null !== $oldField);
            if ($isAlter && !$allow_update) {
                throw new \Exception("Field '$name' already exists in table '$table_name'.");
            }

            $oldForeignKey = (isset($oldField)) ? $oldField->isForeignKey : false;

            $picklist = (isset($field['picklist'])) ? $field['picklist'] : [];
            if (!empty($picklist) && !is_array($picklist)) {
                // accept comma delimited from client side
                $field['picklist'] = array_map('trim', explode(',', trim($picklist, ',')));
            }

            // extras
            $extraTags = ['alias','label','description','picklist','validation','client_info'];
            $extraNew = array_only($field, $extraTags);
            if ($oldField){
                $extraOld = array_only($oldField->toArray(), $extraTags);
                $extraNew = array_diff_assoc($extraNew, $extraOld);
            }

//            if (!empty($picklist)) {
//                $oldPicklist = (isset($oldField)) ? $oldField->picklist : [];
//                if ((count($picklist) !== count($oldPicklist)) ||
//                    empty(array_diff($picklist, $oldPicklist))
//                ) {
//                    $temp['picklist'] = $picklist;
//                }
//            }


            // if same as old, don't bother
            if (!empty($oldField)) {
                $settingsNew = array_except($field, $extraTags);
                $settingsOld = array_except($oldField->toArray(), $extraTags);
                $settingsNew = array_diff_assoc($settingsNew, $settingsOld);

                // if empty, nothing to do here, check extras
                if (empty($settingsNew)) {
                    if (!empty($extraNew)) {
                        $extraNew['table'] = $table_name;
                        $extraNew['field'] = $name;
                        $labels[] = $extraNew;
                    }

                    continue;
                }
            }

            $type = (isset($field['type'])) ? strtolower($field['type']) : '';

            switch ($type) {
                case 'user_id':
                    $extraNew['extra_type'] = 'user_id';
                    break;
                case 'user_id_on_create':
                    $extraNew['extra_type'] = 'user_id_on_create';
                    break;
                case 'user_id_on_update':
                    $extraNew['extra_type'] = 'user_id_on_update';
                    break;
                case 'timestamp_on_create':
                    $extraNew['extra_type'] = 'timestamp_on_create';
                    break;
                case 'timestamp_on_update':
                    $extraNew['extra_type'] = 'timestamp_on_update';
                    break;
                case 'id':
                case 'pk':
                    $pkExtras = $this->getPrimaryKeyCommands($table_name, $name);
                    $extraCommands = array_merge($extraCommands, $pkExtras);
                    break;
            }

            $isForeignKey = (isset($field['is_foreign_key'])) ? boolval($field['is_foreign_key']) : false;
            if (('reference' == $type) || $isForeignKey) {
                // special case for references because the table referenced may not be created yet
                $refTable = (isset($field['ref_table'])) ? $field['ref_table'] : null;
                if (empty($refTable)) {
                    throw new \Exception("Invalid schema detected - no table element for reference type of $name.");
                }

                $refColumns = (isset($field['ref_fields'])) ? $field['ref_fields'] : 'id';
                $refOnDelete = (isset($field['ref_on_delete'])) ? $field['ref_on_delete'] : null;
                $refOnUpdate = (isset($field['ref_on_update'])) ? $field['ref_on_update'] : null;

                if ($this->allowsSeparateForeignConstraint()) {
                    // will get to it later, $refTable may not be there
                    $keyName = $this->makeConstraintName('fk', $table_name, $name);
                    if (!$isAlter || !$oldForeignKey) {
                        $references[] = [
                            'name'       => $keyName,
                            'table'      => $table_name,
                            'column'     => $name,
                            'ref_table'  => $refTable,
                            'ref_fields' => $refColumns,
                            'delete'     => $refOnDelete,
                            'update'     => $refOnUpdate
                        ];
                    }
                }
            }

            // regardless of type
            if (isset($field['is_unique']) && boolval($field['is_unique'])) {
                if ($this->requiresCreateIndex(true, !$isAlter)) {
                    // will get to it later, create after table built
                    $keyName = $this->makeConstraintName('undx', $table_name, $name);
                    $indexes[] = [
                        'name'   => $keyName,
                        'table'  => $table_name,
                        'column' => $name,
                        'unique' => true,
                        'drop'   => $isAlter
                    ];
                }
            } elseif (isset($field['is_index']) && boolval($field['is_index'])) {
                if ($this->requiresCreateIndex(false, !$isAlter)) {
                    // will get to it later, create after table built
                    $keyName = $this->makeConstraintName('ndx', $table_name, $name);
                    $indexes[] = [
                        'name'   => $keyName,
                        'table'  => $table_name,
                        'column' => $name,
                        'drop'   => $isAlter
                    ];
                }
            }

            if ($isAlter) {
                $alterColumns[$name] = $field;
            } else {
                $columns[$name] = $field;
            }

            if (!empty($extraNew)) {
                $extraNew['table'] = $table_name;
                $extraNew['field'] = $name;
                $labels[] = $extraNew;
            }
        }

        return [
            'columns'       => $columns,
            'alter_columns' => $alterColumns,
            'drop_columns'  => $dropColumns,
            'references'    => $references,
            'indexes'       => $indexes,
            'labels'        => $labels,
            'extras'        => $extraCommands
        ];
    }

    /**
     * @param array $info
     */
    protected function translateSimpleColumnTypes(array &$info)
    {
    }

    /**
     * @param array $info
     */
    protected function validateColumnSettings(array &$info)
    {
    }

    /**
     * @param array $info
     *
     * @return string
     * @throws \Exception
     */
    protected function buildColumnDefinition(array $info)
    {
        // This works for most except Oracle
        $type = (isset($info['type'])) ? $info['type'] : null;
        $typeExtras = (isset($info['type_extras'])) ? $info['type_extras'] : null;

        $definition = $type . $typeExtras;

        $allowNull = (isset($info['allow_null'])) ? $info['allow_null'] : null;
        $definition .= ($allowNull) ? ' NULL' : ' NOT NULL';

        $default = (isset($info['db_type'])) ? $info['db_type'] : null;
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
     * Converts an abstract column type into a physical column type.
     * The conversion is done using the type map specified in {@link columnTypes}.
     * These abstract column types are supported (using MySQL as example to explain the corresponding
     * physical types):
     * <ul>
     * <li>pk: an auto-incremental primary key type, will be converted into "int(11) NOT NULL AUTO_INCREMENT PRIMARY
     * KEY"</li>
     * <li>string: string type, will be converted into "varchar(255)"</li>
     * <li>text: a long string type, will be converted into "text"</li>
     * <li>integer: integer type, will be converted into "int(11)"</li>
     * <li>boolean: boolean type, will be converted into "tinyint(1)"</li>
     * <li>float: float number type, will be converted into "float"</li>
     * <li>decimal: decimal number type, will be converted into "decimal"</li>
     * <li>datetime: datetime type, will be converted into "datetime"</li>
     * <li>timestamp: timestamp type, will be converted into "timestamp"</li>
     * <li>time: time type, will be converted into "time"</li>
     * <li>date: date type, will be converted into "date"</li>
     * <li>binary: binary data type, will be converted into "blob"</li>
     * </ul>
     *
     * If the abstract type contains two or more parts separated by spaces or '(' (e.g. "string NOT NULL" or
     * "decimal(10,2)"), then only the first part will be converted, and the rest of the parts will be appended to the
     * conversion result. For example, 'string NOT NULL' is converted to 'varchar(255) NOT NULL'.
     *
     * @param string $info abstract column type
     *
     * @return string physical column type including arguments, null designation and defaults.
     * @throws \Exception
     * @since 1.1.6
     */
    protected function getColumnType($info)
    {
        $out = [];
        $type = '';
        if (is_string($info)) {
            $type = trim($info); // cleanup
        } elseif (is_array($info)) {
            $sql = (isset($info['sql'])) ? $info['sql'] : null;
            if (!empty($sql)) {
                return $sql; // raw SQL statement given, pass it on.
            }

            $out = $info;
            $type = (isset($info['type'])) ? $info['type'] : null;
            if (empty($type)) {
                $type = (isset($info['db_type'])) ? $info['db_type'] : null;
                if (empty($type)) {
                    throw new \Exception("Invalid schema detected - no type or db_type element.");
                }
            }
            $type = trim($type); // cleanup
        }

        if (empty($type)) {
            throw new \Exception("Invalid schema detected - no type definition.");
        }

        //  If there are extras, then pass it on through
        if ((false !== strpos($type, ' ')) || (false !== strpos($type, '('))) {
            return $type;
        }

        $out['type'] = $type;
        $this->translateSimpleColumnTypes($out);
        $this->validateColumnSettings($out);

        return $this->buildColumnDefinition($out);
    }

    /**
     * Builds a SQL statement for creating a new DB table.
     *
     * The columns in the new  table should be specified as name-definition pairs (e.g. 'name'=>'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which can contain an abstract DB type.
     * The {@link getColumnType} method will be invoked to convert any abstract type into a physical one.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * inserted into the generated SQL.
     *
     * @param string $table   the name of the table to be created. The name will be properly quoted by the method.
     * @param array  $columns the columns (name=>definition) in the new table.
     * @param string $options additional SQL fragment that will be appended to the generated SQL.
     *
     * @return string the SQL statement for creating a new DB table.
     * @since 1.1.6
     */
    public function createTable($table, $columns, $options = null)
    {
        $cols = [];
        foreach ($columns as $name => $type) {
            if (is_string($name)) {
                $cols[] = "\t" . $this->quoteColumnName($name) . ' ' . $this->getColumnType($type);
            } else {
                $cols[] = "\t" . $type;
            }
        }
        $sql = "CREATE TABLE " . $this->quoteTableName($table) . " (\n" . implode(",\n", $cols) . "\n)";

        return $options === null ? $sql : $sql . ' ' . $options;
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
        return 'RENAME TABLE ' . $this->quoteTableName($table) . ' TO ' . $this->quoteTableName($newName);
    }

    /**
     * Builds a SQL statement for dropping a DB table.
     *
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for dropping a DB table.
     * @since 1.1.6
     */
    public function dropTable($table)
    {
        return "DROP TABLE " . $this->quoteTableName($table);
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
        return "TRUNCATE TABLE " . $this->quoteTableName($table);
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
        return
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' ADD ' .
            $this->quoteColumnName($column) .
            ' ' .
            $this->getColumnType($type);
    }

    /**
     * Builds a SQL statement for dropping a DB column.
     *
     * @param string $table  the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     *
     * @return string the SQL statement for dropping a DB column.
     * @since 1.1.6
     */
    public function dropColumn($table, $column)
    {
        return "ALTER TABLE " . $this->quoteTableName($table) . " DROP COLUMN " . $this->quoteColumnName($column);
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
        return
            "ALTER TABLE " .
            $this->quoteTableName($table) .
            " RENAME COLUMN " .
            $this->quoteColumnName($name) .
            " TO " .
            $this->quoteColumnName($newName);
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
        return
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' CHANGE ' .
            $this->quoteColumnName($column) .
            ' ' .
            $this->quoteColumnName($column) .
            ' ' .
            $this->getColumnType($definition);
    }

    public function makeConstraintName($prefix, $table, $column)
    {
        $temp = $prefix . '_' . str_replace('.', '_', $table) . '_' . $column;

        return $temp;
    }

    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
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
     * @return string the SQL statement for adding a foreign key constraint to an existing table.
     * @since 1.1.6
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($columns as $i => $col) {
            $columns[$i] = $this->quoteColumnName($col);
        }
        $refColumns = preg_split('/\s*,\s*/', $refColumns, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($refColumns as $i => $col) {
            $refColumns[$i] = $this->quoteColumnName($col);
        }
        $sql =
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' ADD CONSTRAINT ' .
            $this->quoteColumnName($name) .
            ' FOREIGN KEY (' .
            implode(', ', $columns) .
            ')' .
            ' REFERENCES ' .
            $this->quoteTableName($refTable) .
            ' (' .
            implode(', ', $refColumns) .
            ')';
        if ($delete !== null) {
            $sql .= ' ON DELETE ' . $delete;
        }
        if ($update !== null) {
            $sql .= ' ON UPDATE ' . $update;
        }

        return $sql;
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
        return 'ALTER TABLE ' . $this->quoteTableName($table) . ' DROP CONSTRAINT ' . $this->quoteColumnName($name);
    }

    public function requiresCreateIndex($unique = false, $on_create_table = false)
    {
        return true;
    }

    public function allowsSeparateForeignConstraint()
    {
        return true;
    }

    /**
     * Builds a SQL statement for creating a new index.
     *
     * @param string  $name   the name of the index. The name will be properly quoted by the method.
     * @param string  $table  the table that the new index will be created for. The table name will be properly quoted
     *                        by the method.
     * @param string  $column the column(s) that should be included in the index. If there are multiple columns, please
     *                        separate them by commas. Each column name will be properly quoted by the method, unless a
     *                        parenthesis is found in the name.
     * @param boolean $unique whether to add UNIQUE constraint on the created index.
     *
     * @return string the SQL statement for creating a new index.
     * @since 1.1.6
     */
    public function createIndex($name, $table, $column, $unique = false)
    {
        $cols = [];
        $columns = preg_split('/\s*,\s*/', $column, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($columns as $col) {
            if (strpos($col, '(') !== false) {
                $cols[] = $col;
            } else {
                $cols[] = $this->quoteColumnName($col);
            }
        }

        return
            ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ') .
            $this->quoteTableName($name) .
            ' ON ' .
            $this->quoteTableName($table) .
            ' (' .
            implode(', ', $cols) .
            ')';
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
        return 'DROP INDEX ' . $this->quoteTableName($name) . ' ON ' . $this->quoteTableName($table);
    }

    /**
     * Builds a SQL statement for adding a primary key constraint to an existing table.
     *
     * @param string       $name    the name of the primary key constraint.
     * @param string       $table   the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     *                              Array value can be passed since 1.1.14.
     *
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     * @since 1.1.13
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        if (is_string($columns)) {
            $columns = preg_split('/\s*,\s*/', $columns, -1, PREG_SPLIT_NO_EMPTY);
        }
        foreach ($columns as $i => $col) {
            $columns[$i] = $this->quoteColumnName($col);
        }

        return
            'ALTER TABLE ' .
            $this->quoteTableName($table) .
            ' ADD CONSTRAINT ' .
            $this->quoteColumnName($name) .
            '  PRIMARY KEY (' .
            implode(', ', $columns) .
            ' )';
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
        return 'ALTER TABLE ' . $this->quoteTableName($table) . ' DROP CONSTRAINT ' . $this->quoteColumnName($name);
    }

    public function getPrimaryKeyCommands($table, $column)
    {
        return [];
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
        switch ($field_info->dbType) {
            default :
                $out = ($as_quoted_string) ? $field_info->rawName : $field_info->name;
                if (!empty($field_info->alias)) {
                    if ($as_quoted_string){
                        $out .= ' AS ' . $this->quoteColumnName($field_info->alias);
                    } else {
                        $out .= ' AS ' . $field_info->alias;
                    }
                }
                break;
        }

        return $out;
    }

    /**
     * @param ColumnSchema $field_info
     *
     * @return array
     */
    public function parseFieldForBinding(ColumnSchema $field_info)
    {
        $pdoType = ($field_info->allowNull) ? null : $field_info->pdoType;
        $phpType = (is_null($pdoType)) ? $field_info->phpType : null;

        return ['name' => $field_info->getName(true), 'pdo_type' => $pdoType, 'php_type' => $phpType];
    }

    /**
     * @param bool $update
     *
     * @return mixed
     */
    public function getTimestampForSet($update = false)
    {
        return new Expression('(NOW())');
    }

    public function parseValueForSet($value, $field_info)
    {
        return $value;
    }

    public static function formatValue($value, $type)
    {
        $type = strtolower(strval($type));
        switch ($type) {
            case 'int':
            case 'integer':
                return intval($value);

            case 'decimal':
            case 'double':
            case 'float':
                return floatval($value);

            case 'boolean':
            case 'bool':
                return Scalar::boolval($value);

            case 'string':
                return strval($value);

            case 'time':
            case 'date':
            case 'datetime':
            case 'timestamp':
                $cfgFormat = static::getDateTimeFormat($type);

                return static::formatDateTime($cfgFormat, $value);
        }

        return $value;
    }

    public static function getDateTimeFormat($type)
    {
        switch (strtolower(strval($type))) {
            case 'time':
                return \Config::get('df.db_time_format');

            case 'date':
                return \Config::get('df.db_date_format');

            case 'datetime':
                return \Config::get('df.db_datetime_format');

            case 'timestamp':
                return \Config::get('df.db_timestamp_format');
        }

        return null;
    }

    public static function formatDateTime($out_format, $in_value = null, $in_format = null)
    {
        //  If value is null, current date and time are returned
        if (!empty($out_format)) {
            $in_value = (is_string($in_value) || is_null($in_value)) ? $in_value : strval($in_value);
            if (!empty($in_format)) {
                if (false === $date = \DateTime::createfromFormat($in_format, $in_value)) {
                    \Log::error("Failed to format datetime from '$in_value'' to '$in_format'");

                    return $in_value;
                }
            } else {
                $date = new \DateTime($in_value);
            }

            return $date->format($out_format);
        }

        return $in_value;
    }
}
