<?php
namespace DreamFactory\Core\Database\Sqlite;

/**
 * ColumnSchema class describes the column meta data of a SQLite table.
 */
class ColumnSchema extends \DreamFactory\Core\Database\ColumnSchema
{
    /**
     * Extracts the default value for the column.
     * The value is typecasted to correct PHP type.
     *
     * @param mixed $defaultValue the default value obtained from metadata
     */
    public function extractDefault($defaultValue)
    {
        if ($this->dbType === 'timestamp' && $defaultValue === 'CURRENT_TIMESTAMP') {
            $this->defaultValue = null;
        } else {
            $this->defaultValue = $this->typecast(strcasecmp($defaultValue, 'null') ? $defaultValue : null);
        }

        if ($this->phpType === 'string' &&
            $this->defaultValue !== null
        ) // PHP 5.2.6 adds single quotes while 5.2.0 doesn't
        {
            $this->defaultValue = trim($this->defaultValue, "'\"");
        }
    }
}
