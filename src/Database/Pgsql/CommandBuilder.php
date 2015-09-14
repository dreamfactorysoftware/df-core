<?php
namespace DreamFactory\Core\Database\Pgsql;

use DreamFactory\Core\Database\Command;
use DreamFactory\Core\Database\Expression;

/**
 * CommandBuilder provides basic methods to create query commands for tables.
 */
class CommandBuilder extends \DreamFactory\Core\Database\CommandBuilder
{
    /**
     * @var integer the last insertion ID
     */
    public $returnID;

    /**
     * Returns the last insertion ID for the specified table.
     *
     * @param mixed $table the table schema ({@link TableSchema}) or the table name (string).
     *
     * @return mixed last insertion id. Null is returned if no sequence name.
     */
    public function getLastInsertID($table)
    {
        return $this->returnID;
    }

    /**
     * Creates an INSERT command.
     *
     * @param mixed $table the table schema ({@link TableSchema}) or the table name (string).
     * @param array $data  data to be inserted (column name=>column value). If a key is not a valid column name, the
     *                     corresponding value will be ignored.
     *
     * @return Command insert command
     */
    public function createInsertCommand($table, $data)
    {
        $this->ensureTable($table);
        $fields = array();
        $values = array();
        $placeholders = array();
        $i = 0;
        foreach ($data as $name => $value) {
            if (($column = $table->getColumn($name)) !== null && ($value !== null || $column->allowNull)) {
                $fields[] = $column->rawName;
                if ($value instanceof Expression) {
                    $placeholders[] = $value->expression;
                    foreach ($value->params as $n => $v) {
                        $values[$n] = $v;
                    }
                } else {
                    $placeholders[] = self::PARAM_PREFIX . $i;
                    $values[self::PARAM_PREFIX . $i] = $column->typecast($value);
                    $i++;
                }
            }
        }

        $sql =
            "INSERT INTO {$table->rawName} (" .
            implode(', ', $fields) .
            ') VALUES (' .
            implode(', ', $placeholders) .
            ')';

        if (is_string($table->primaryKey) &&
            ($column = $table->getColumn($table->primaryKey)) !== null &&
            $column->type !== 'string'
        ) {
            $sql .= ' RETURNING ' . $column->rawName . ' INTO :RETURN_ID';
            $command = $this->getDbConnection()->createCommand($sql);
            $command->bindParam(':RETURN_ID', $this->returnID, \PDO::PARAM_INT, 12);
            $table->sequenceName = 'RETURN_ID';
        } else {
            $command = $this->getDbConnection()->createCommand($sql);
        }

        foreach ($values as $name => $value) {
            $command->bindValue($name, $value);
        }

        return $command;
    }

    /**
     * Returns default value of the integer/serial primary key. Default value means that the next
     * autoincrement/sequence value would be used.
     *
     * @return string default value of the integer/serial primary key.
     * @since 1.1.14
     */
    protected function getIntegerPrimaryKeyDefaultValue()
    {
        return 'DEFAULT';
    }
}