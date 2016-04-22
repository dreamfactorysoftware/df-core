<?php
namespace DreamFactory\Core\Database\Schema\Sqlanywhere;

/**
 * ColumnSchema class describes the column meta data of a Sap table.
 */
class ColumnSchema extends \DreamFactory\Core\Database\ColumnSchema
{
    /**
     * Extracts the PHP type from DB type.
     *
     * @param string $dbType DB type
     */
    public function extractType($dbType)
    {
        parent::extractType($dbType);

        $simpleType = strstr($dbType, '(', true);
        $simpleType = strtolower($simpleType ?: $dbType);

        switch ($simpleType) {
            case 'long varchar':
                $this->type = static::TYPE_TEXT;
                break;
            case 'long nvarchar':
                $this->type = static::TYPE_TEXT;
                $this->supportsMultibyte = true;
                break;
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
        if ('autoincrement' === $defaultValue) {
            $this->defaultValue = null;
            $this->autoIncrement = true;
        } elseif (('(NULL)' === $defaultValue) || ('' === $defaultValue)) {
            $this->defaultValue = null;
        } elseif ($this->type === static::TYPE_BOOLEAN) {
            if ('1' === $defaultValue) {
                $this->defaultValue = true;
            } elseif ('0' === $defaultValue) {
                $this->defaultValue = false;
            } else {
                $this->defaultValue = null;
            }
        } elseif ($this->type === static::TYPE_TIMESTAMP) {
            $this->defaultValue = null;
            if ('current timestamp' === $defaultValue) {
                $this->defaultValue = ['expression' => 'CURRENT TIMESTAMP'];
                $this->type = static::TYPE_TIMESTAMP_ON_CREATE;
            } elseif ('timestamp' === $defaultValue) {
                $this->defaultValue = ['expression' => 'TIMESTAMP'];
                $this->type = static::TYPE_TIMESTAMP_ON_UPDATE;
            }
        } else {
            parent::extractDefault(str_replace(['(', ')', "'"], '', $defaultValue));
        }
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     * We do nothing here, since sizes and precisions have been computed before.
     *
     * @param string $dbType the column's DB type
     */
    public function extractLimit($dbType)
    {
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
        if ($this->phpType === 'boolean') {
            return $value ? 1 : 0;
        } else {
            return parent::typecast($value);
        }
    }

    public function parseFieldForSelect($as_quoted_string = false)
    {
        $field = ($as_quoted_string) ? $this->rawName : $this->name;
        $alias = $this->getName(true);
        if ($as_quoted_string && !ctype_alnum($alias)){
            $alias = '['.$alias.']';
        }
        switch ($this->dbType) {
//            case 'datetime':
//            case 'datetimeoffset':
//                return "(CONVERT(nvarchar(30), $field, 127)) AS $alias";
            case 'geometry':
            case 'geography':
            case 'hierarchyid':
                return "($field.ToString()) AS $alias";
            default :
                return parent::parseFieldForSelect($as_quoted_string);
        }
    }
}
