<?php
namespace DreamFactory\Core\Database\Pgsql;

/**
 * ColumnSchema class describes the column meta data of a PostgreSQL table.
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
        if (strpos($dbType, '[') !== false || strpos($dbType, 'char') !== false || strpos($dbType, 'text') !== false) {
            $this->type = 'string';
        } elseif (preg_match('/(real|float|double)/', $dbType)) {
            $this->type = 'double';
        } elseif (preg_match('/(integer|oid|serial|smallint)/', $dbType)) {
            $this->type = 'integer';
        }
    }

    /**
     * Extracts size, precision and scale information from column's DB type.
     *
     * @param string $dbType the column's DB type
     */
    public function extractLimit($dbType)
    {
        if (strpos($dbType, '(')) {
            if (preg_match('/^time.*\((.*)\)/', $dbType, $matches)) {
                $this->precision = (int)$matches[1];
            } elseif (preg_match('/\((.*)\)/', $dbType, $matches)) {
                $values = explode(',', $matches[1]);
                $this->size = $this->precision = (int)$values[0];
                if (isset($values[1])) {
                    $this->scale = (int)$values[1];
                }
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
        if ($defaultValue === 'true') {
            $this->defaultValue = true;
        } elseif ($defaultValue === 'false') {
            $this->defaultValue = false;
        } elseif (strpos($defaultValue, 'nextval') === 0) {
            $this->defaultValue = null;
        } elseif (preg_match('/^\'(.*)\'::/', $defaultValue, $matches)) {
            $this->defaultValue = $this->typecast(str_replace("''", "'", $matches[1]));
        } elseif (preg_match('/^(-?\d+(\.\d*)?)(::.*)?$/', $defaultValue, $matches)) {
            $this->defaultValue = $this->typecast($matches[1]);
        } else {
            // could be a internal function call like setting uuids
            $this->defaultValue = $defaultValue;
        }
    }
}