<?php
namespace DreamFactory\Core\Database\Schema;

/**
 * ColumnSchema class describes the column meta data of a database table.
 */
class ColumnSchema extends NamedResourceSchema
{
    /**
     * @var string the DB type of this column.
     */
    public $dbType;
    /**
     * @var string the DreamFactory simple type of this column.
     */
    public $type;
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
    public $allowNull = true;
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
     * @var boolean whether this column is virtual, i.e. doesn't exist in the database
     */
    public $isVirtual = false;
    /**
     * @var boolean whether this column is a virtual column that is an aggregate function
     */
    public $isAggregate = false;
    /**
     * @var string comment of this column. Default value is empty string which means that no comment
     * has been set for the column. Null value means that RDBMS does not support column comments
     * at all (SQLite) or comment retrieval for the active RDBMS is not yet supported by the framework.
     */
    public $comment = '';

    public function fill(array $settings)
    {
        foreach ($settings as $key => $value) {
            if (empty($value)) {
                switch ($key) {
                    case 'type':
                    case 'db_function':
                    case 'dbFunction':
                    case 'validation':
                        // don't let extras override these
                        continue;
                        break;
                }
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

    public function getDbFunction($operation)
    {
        if (!empty($this->dbFunction)) {
            foreach ($this->dbFunction as $functionInfo) {
                if (in_array($operation, (array)array_get($functionInfo, 'use'))) {
                    return array_get($functionInfo, 'function');
                }
            }
        }

        return null;
    }

    public function toArray($use_alias = false)
    {
        $out = [
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
            'is_virtual'         => $this->isVirtual,
            'is_aggregate'       => $this->isAggregate,
        ];

        return array_merge(parent::toArray($use_alias), $out);
    }
}
