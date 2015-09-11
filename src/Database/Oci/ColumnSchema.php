<?php
namespace DreamFactory\Core\Database\Oci;

/**
 * ColumnSchema class describes the column meta data of an Oracle table.
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
        if (strpos($dbType, 'FLOAT') !== false) {
            $this->phpType = 'double';
        }

        if (strpos($dbType, 'NUMBER') !== false || strpos($dbType, 'INTEGER') !== false) {
            if (strpos($dbType, '(') && preg_match('/\((.*)\)/', $dbType, $matches)) {
                $values = explode(',', $matches[1]);
                if (isset($values[1]) and (((int)$values[1]) > 0)) {
                    $this->phpType = 'double';
                } else {
                    $this->phpType = 'integer';
                }
            } else {
                $this->phpType = 'double';
            }
        } else {
            $this->phpType = 'string';
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
        if (stripos($defaultValue, 'timestamp') !== false) {
            $this->defaultValue = null;
        } else {
            parent::extractDefault($defaultValue);
        }
    }
}
