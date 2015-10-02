<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Library\Utility\Inflector;

/**
 * ColumnSchema class describes the column meta data of a database table.
 */
class ColumnSchema
{
    /**
     * The followings are the supported abstract column data types.
     */
    const TYPE_ID        = 'id';
    const TYPE_REF       = 'reference';
    const TYPE_STRING    = 'string';
    const TYPE_TEXT      = 'text';
    const TYPE_INTEGER   = 'integer';
    const TYPE_BIGINT    = 'bigint';
    const TYPE_FLOAT     = 'float';
    const TYPE_DOUBLE    = 'double';
    const TYPE_DECIMAL   = 'decimal';
    const TYPE_DATETIME  = 'datetime';
    const TYPE_TIMESTAMP = 'timestamp';
    const TYPE_TIME      = 'time';
    const TYPE_DATE      = 'date';
    const TYPE_BINARY    = 'binary';
    const TYPE_BOOLEAN   = 'boolean';
    const TYPE_MONEY     = 'money';

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
    public $refFields;
    /**
     * @var boolean whether this column is auto-incremental
     * @since 1.1.7
     */
    public $autoIncrement = false;
    /**
     * @var boolean whether this column supports
     * @since 1.1.7
     */
    public $supportsMultibyte = false;
    /**
     * @var boolean whether this column is auto-incremental
     * @since 1.1.7
     */
    public $fixedLength = false;
    /**
     * @var string the allowed picklist values for this column.
     */
    public $picklist;
    /**
     * @var string Additional validations for this column.
     */
    public $validation;
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
            case static::TYPE_BOOLEAN:
                return 'boolean';

            case static::TYPE_INTEGER:
            case static::TYPE_ID:
            case static::TYPE_REF:
                return 'integer';

            case static::TYPE_DECIMAL:
            case static::TYPE_DOUBLE:
            case static::TYPE_FLOAT:
            case static::TYPE_MONEY:
                return 'double';

            default:
                return 'string';
        }
    }

    /**
     * Extracts the PHP PDO type from DF type.
     *
     * @param string $type DF type
     *
     * @return int|null
     */
    public static function extractPdoType($type)
    {
        switch ($type) {
            case static::TYPE_BOOLEAN:
                return \PDO::PARAM_BOOL;

            case static::TYPE_INTEGER:
            case static::TYPE_ID:
            case static::TYPE_REF:
                return \PDO::PARAM_INT;

            case static::TYPE_STRING:
                return \PDO::PARAM_STR;
        }

        return null;
    }

    /**
     * Extracts the DreamFactory simple type from DB type.
     *
     * @param string $dbType DB type
     */
    public function extractType($dbType)
    {
        $simpleType = strstr($dbType, '(', true);
        $simpleType = strtolower($simpleType ?: $dbType);

        switch ($simpleType) {
            case 'bit':
            case (false !== strpos($simpleType, 'bool')):
                $this->type = static::TYPE_BOOLEAN;
                break;

            case 'number': // Oracle for boolean, integers and decimals
                if ($this->size == 1) {
                    $this->type = static::TYPE_BOOLEAN;
                } elseif (empty($this->scale)) {
                    $this->type = static::TYPE_INTEGER;
                } else {
                    $this->type = static::TYPE_DECIMAL;
                }
                break;

            case 'decimal':
            case 'numeric':
            case 'percent':
                $this->type = static::TYPE_DECIMAL;
                break;

            case (false !== strpos($simpleType, 'double')):
                $this->type = static::TYPE_DOUBLE;
                break;

            case 'real':
            case (false !== strpos($simpleType, 'float')):
                if ($this->size == 53) {
                    $this->type = static::TYPE_DOUBLE;
                } else {
                    $this->type = static::TYPE_FLOAT;
                }
                break;

            case (false !== strpos($simpleType, 'money')):
                $this->type = static::TYPE_MONEY;
                break;

            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'int':
            case 'integer':
                // watch out for point here!
                if ($this->size == 1) {
                    $this->type = static::TYPE_BOOLEAN;
                } else {
                    $this->type = static::TYPE_INTEGER;
                }
                break;

            case 'bigint':
                // bigint too big to represent as number in php
                $this->type = static::TYPE_BIGINT;
                break;

            case (false !== strpos($simpleType, 'timestamp')):
            case 'datetimeoffset': //  MSSQL
                $this->type = static::TYPE_TIMESTAMP;
                break;

            case (false !== strpos($simpleType, 'datetime')):
                $this->type = static::TYPE_DATETIME;
                break;

            case 'date':
                $this->type = static::TYPE_DATE;
                break;

            case (false !== strpos($simpleType, 'time')):
                $this->type = static::TYPE_TIME;
                break;

            case (false !== strpos($simpleType, 'binary')):
            case (false !== strpos($simpleType, 'blob')):
                $this->type = static::TYPE_BINARY;
                break;

            //	String types
            case (false !== strpos($simpleType, 'clob')):
            case (false !== strpos($simpleType, 'text')):
                $this->type = static::TYPE_TEXT;
                break;

            case 'varchar':
                if ($this->size == -1) {
                    $this->type = static::TYPE_TEXT; // varchar(max) in MSSQL
                } else {
                    $this->type = static::TYPE_STRING;
                }
                break;

            case 'string':
            case (false !== strpos($simpleType, 'char')):
            default:
                $this->type = static::TYPE_STRING;
                break;
        }

        $this->phpType = static::extractPhpType($this->type);
        $this->pdoType = static::extractPdoType($this->type);
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     *
     * @param string $dbType the column's DB type
     */
    public function extractLimit($dbType)
    {
        if (strpos($dbType, '(') && preg_match('/\((.*)\)/', $dbType, $matches)) {
            $values = explode(',', $matches[1]);
            $this->size = (int)$values[0];
            if (isset($values[1])) {
                $this->precision = (int)$values[0];
                $this->scale = (int)$values[1];
            }
        }
    }

    /**
     * Extracts the default value for the column.
     * The value is typecasted to correct PHP type.
     *
     * @param mixed $defaultValue the default value obtained from metadata
     */
    public function extractDefault($defaultValue)
    {
        $this->defaultValue = $this->typecast($defaultValue);
    }

    /**
     * Converts the input value to the type that this column is of.
     *
     * @param mixed $value input value
     *
     * @return mixed converted value
     */
    public function typecast($value)
    {
        if (gettype($value) === $this->phpType || $value === null || $value instanceof Expression) {
            return $value;
        }
        if ($value === '' && $this->allowNull) {
            return ($this->phpType === 'string') ? '' : null;
        }
        switch ($this->phpType) {
            case 'string':
                return (string)$value;
            case 'integer':
                return (integer)$value;
            case 'boolean':
                return (boolean)$value;
            case 'double':
            default:
                return $value;
        }
    }

    /**
     * @param $dbType
     *
     * @return bool
     */
    public function extractMultiByteSupport($dbType)
    {
        switch ($dbType) {
            case (false !== strpos($dbType, 'national')):
            case (false !== strpos($dbType, 'nchar')):
            case (false !== strpos($dbType, 'nvarchar')):
                $this->supportsMultibyte = true;
                break;
        }
    }

    /**
     * @param $dbType
     *
     * @return bool
     */
    public function extractFixedLength($dbType)
    {
        switch ($dbType) {
            case ((false !== strpos($dbType, 'char')) && (false === strpos($dbType, 'var'))):
            case 'binary':
                $this->fixedLength = true;
                break;
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
            'is_foreign_key'     => $this->isForeignKey,
            'is_unique'          => $this->isUnique,
            'is_index'           => $this->isIndex,
            'ref_table'          => $this->refTable,
            'ref_fields'         => $this->refFields,
            'picklist'           => $this->picklist,
            'validation'         => $this->validation
        ];

        if (!$use_alias) {
            $out['alias'] = $this->alias;
        }

        return $out;
    }
}
