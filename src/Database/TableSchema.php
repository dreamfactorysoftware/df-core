<?php
namespace DreamFactory\Core\Database;

/**
 * TableSchema is the base class for representing the metadata of a database table.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific table metadata.
 *
 * TableSchema provides the following information about a table:
 * <ul>
 * <li>{@link name}</li>
 * <li>{@link rawName}</li>
 * <li>{@link columns}</li>
 * <li>{@link primaryKey}</li>
 * <li>{@link foreignKeys}</li>
 * <li>{@link sequenceName}</li>
 * </ul>
 *
 * @property array $columnNames List of column names.
 */
class TableSchema extends TableNameSchema
{
    /**
     * @var string name of the schema that this table belongs to.
     */
    public $schemaName;
    /**
     * @var string name of this table.
     */
    public $name;
    /**
     * @var string raw name of this table. This is the quoted version of table name with optional schema name. It can
     *      be directly used in SQLs.
     */
    public $rawName;
    /**
     * @var string public display name of this table. This is the table name with optional non-default schema name. It
     *      is to be used by clients.
     */
    public $displayName;
    /**
     * @var string Optional field of this table that may contain a displayable name for each row/record.
     */
    public $nameField;
    /**
     * @var string|array primary key name of this table. If composite key, an array of key names is returned.
     */
    public $primaryKey;
    /**
     * @var string sequence name for the primary key. Null if no sequence.
     */
    public $sequenceName;
    /**
     * @var array foreign keys of this table. The array is indexed by column name. Each value is an array of foreign
     *      table name and foreign column name.
     */
    public $foreignKeys = [];
    /**
     * @var array relationship metadata of this table. Each array element is a RelationSchema object, indexed by
     *      relation name.
     */
    public $relations = [];
    /**
     * @var array column metadata of this table. Each array element is a ColumnSchema object, indexed by column name.
     */
    public $columns = [];

    /**
     * Sets the named column metadata.
     *
     * @param ColumnSchema $schema
     */
    public function addColumn(ColumnSchema $schema)
    {
        $this->columns[strtolower($schema->name)] = $schema;
    }

    /**
     * Gets the named column metadata.
     * This is a convenient method for retrieving a named column even if it does not exist.
     *
     * @param string $name column name
     *
     * @return ColumnSchema metadata of the named column. Null if the named column does not exist.
     */
    public function getColumn($name)
    {
        $key = strtolower($name);

        return (isset($this->columns[$key])) ? $this->columns[$key] : null;
    }

    /**
     * @return array list of column names
     */
    public function getColumnNames()
    {
        return array_keys($this->columns);
    }

    public function addRelation($type, $ref_table, $ref_field, $field, $join = null)
    {
        $relation = new RelationSchema($type, $ref_table, $ref_field, $field, $join);

        $this->relations[strtolower($relation->name)] = $relation;
    }

    /**
     * Gets the named relation metadata.
     *
     * @param string $name relation name
     *
     * @return RelationSchema metadata of the named relation. Null if the named relation does not exist.
     */
    public function getRelation($name)
    {
        $key = strtolower($name);

        return (isset($this->relations[$key])) ? $this->relations[$key] : null;
    }

    /**
     * @return array list of column names
     */
    public function getRelationNames()
    {
        return array_keys($this->columns);
    }

    public function toArray($use_alias = false)
    {
        $out = parent::toArray($use_alias);

        $fields = [];
        /** @var ColumnSchema $column */
        foreach ($this->columns as $column) {
            $fields[] = $column->toArray($use_alias);
        }
        $out['field'] = $fields;

        $relations = [];
        /** @var RelationSchema $relation */
        foreach ($this->relations as $relation) {
            $relations[] = $relation->toArray();
        }
        $out['related'] = $relations;

        $out['primary_key'] = $this->primaryKey;
        $out['name_field'] = $this->nameField;
        $out['name'] = $this->displayName;

        return $out;
    }
}
