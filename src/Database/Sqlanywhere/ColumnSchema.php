<?php
namespace DreamFactory\Core\Database\Sqlanywhere;

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
                $this->type = 'text';
                break;
            case 'long nvarchar':
                $this->type = 'text';
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
        } elseif ($defaultValue == '(NULL)') {
            $this->defaultValue = null;
        } elseif ($this->type === 'boolean') {
            if ('1' === $defaultValue) {
                $this->defaultValue = true;
            } elseif ('0' === $defaultValue) {
                $this->defaultValue = false;
            } else {
                $this->defaultValue = null;
            }
        } elseif ($this->type === 'timestamp') {
            $this->defaultValue = null;
            if ($defaultValue === 'current timestamp') {
                $this->defaultValue = ['expression' => 'CURRENT TIMESTAMP'];
                $this->type = 'timestamp_on_create';
            } elseif ($defaultValue === 'timestamp') {
                $this->defaultValue = ['expression' => 'TIMESTAMP'];
                $this->type = 'timestamp_on_update';
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
}
