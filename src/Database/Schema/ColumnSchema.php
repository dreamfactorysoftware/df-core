<?php
namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Library\Utility\Inflector;

/**
 * ColumnSchema class describes the column meta data of a database table.
 */
class ColumnSchema
{
    /**
     * @var string name of this column (without quotes).
     */
    public $name;
    /**
     * @var string raw name of this column. This is the quoted name that can be used in SQL queries.
     */
    public $rawName;
    /**
     * @var string Optional alias for this column.
     */
    public $alias;
    /**
     * @var string Optional label for this column.
     */
    public $label;
    /**
     * @var string the DB type of this column.
     */
    public $dbType;
    /**
     * @var string the DreamFactory simple type of this column.
     */
    public $type;
    /**
     * @var string the PHP type of this column.
     */
    public $phpType;
    /**
     * @var string the PHP PDO type of this column.
     */
    public $pdoType;
    /**
     * @var string the DF extra type of this column.
     */
    public $extraType;
    /**
     * @var mixed default value of this column
     */
    public $defaultValue;
    /**
     * @var integer size of the column.
     */
    public $size;
    /**
     * @var integer precision of the column data, if it is numeric.
     */
    public $precision;
    /**
     * @var integer scale of the column data, if it is numeric.
     */
    public $scale;
    /**
     * @var boolean whether this column can be null.
     */
    public $allowNull = false;
    /**
     * @var boolean whether this column is a primary key
     */
    public $isPrimaryKey = false;
    /**
     * @var boolean whether this column has a unique constraint
     */
    public $isUnique = false;
    /**
     * @var boolean whether this column is indexed
     */
    public $isIndex = false;
    /**
     * @var boolean whether this column is a foreign key
     */
    public $isForeignKey = false;
    /**
     * @var string if a foreign key, then this is referenced table name
     */
    public $refTable;
    /**
     * @var string if a foreign key, then this is the referenced fields of the referenced table
     */
    public $refField;
    /**
     * @var string if a foreign key, then what to do with this field's value when the foreign is updated
     */
    public $refOnUpdate;
    /**
     * @var string if a foreign key, then what to do with this field's value when the foreign is deleted
     */
    public $refOnDelete;
    /**
     * @var boolean whether this column is auto-incremental
     */
    public $autoIncrement = false;
    /**
     * @var boolean whether this column supports
     */
    public $supportsMultibyte = false;
    /**
     * @var boolean whether this column is auto-incremental
     */
    public $fixedLength = false;
    /**
     * @var array the allowed picklist values for this column.
     */
    public $picklist;
    /**
     * @var array Additional validations for this column.
     */
    public $validation;
    /**
     * @var array DB function to use for this column.
     */
    public $dbFunction;
    /**
     * @var string Optional description of this column.
     */
    public $description;
    /**
     * @var string comment of this column. Default value is empty string which means that no comment
     * has been set for the column. Null value means that RDBMS does not support column comments
     * at all (SQLite) or comment retrieval for the active RDBMS is not yet supported by the framework.
     */
    public $comment = '';

    public function __construct(array $settings)
    {
        $this->fill($settings);
    }

    public function fill(array $settings)
    {
        foreach ($settings as $key => $value) {
            if (('extra_type' === $key) && !empty($value)) {
                $this->type = $value;
                continue;
            }
            if (!property_exists($this, $key)) {
                // try camel cased
                $camel = camel_case($key);
                if (property_exists($this, $camel)) {
                    $this->{$camel} = $value;
                    continue;
                }
            }
            // set real and virtual
            $this->{$key} = $value;
        }
    }

    public function getRequired()
    {
        if (property_exists($this, 'required')) {
            return $this->{'required'};
        }

        if ($this->allowNull || (isset($this->defaultValue)) || $this->autoIncrement) {
            return false;
        }

        return true;
    }

    public function getName($use_alias = false)
    {
        return ($use_alias && !empty($this->alias)) ? $this->alias : $this->name;
    }

    public function getLabel()
    {
        return (empty($this->label)) ? Inflector::camelize($this->getName(true), '_', true) : $this->label;
    }

    public function getDbFunction()
    {
        $function = 'NULL';
        if (!empty($this->dbFunction) && isset($this->dbFunction['function'])) {
            $function = $this->dbFunction['function'];
        }

        return $function;
    }

    public function getDbFunctionType()
    {
        $type = 'string';
        if (!empty($this->dbFunction) && isset($this->dbFunction['type'])) {
            $type = $this->dbFunction['type'];
        }

        return $type;
    }

    public function isAggregate()
    {
        if (!empty($this->dbFunction) && isset($this->dbFunction['aggregate'])) {
            return filter_var($this->dbFunction['aggregate'], FILTER_VALIDATE_BOOLEAN);
        }

        return false;
    }

    public function toArray($use_alias = false)
    {
        $out = [
            'name'               => $this->getName($use_alias),
            'label'              => $this->getLabel(),
            'description'        => $this->description,
            'type'               => $this->type,
            'db_type'            => $this->dbType,
            'length'             => $this->size,
            'precision'          => $this->precision,
            'scale'              => $this->scale,
            'default'            => $this->defaultValue,
            'required'           => $this->getRequired(),
            'allow_null'         => $this->allowNull,
            'fixed_length'       => $this->fixedLength,
            'supports_multibyte' => $this->supportsMultibyte,
            'auto_increment'     => $this->autoIncrement,
            'is_primary_key'     => $this->isPrimaryKey,
            'is_unique'          => $this->isUnique,
            'is_index'           => $this->isIndex,
            'is_foreign_key'     => $this->isForeignKey,
            'ref_table'          => $this->refTable,
            'ref_field'          => $this->refField,
            'ref_on_update'      => $this->refOnUpdate,
            'ref_on_delete'      => $this->refOnDelete,
            'picklist'           => $this->picklist,
            'validation'         => $this->validation,
            'db_function'        => $this->dbFunction,
        ];

        if (!$use_alias) {
            $out = array_merge(['alias' => $this->alias], $out);
        }

        return $out;
    }
}
