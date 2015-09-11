<?php
namespace DreamFactory\Core\Database\Sqlite;

use DreamFactory\Core\Database\TableSchema;
use DreamFactory\Core\Database\Command;

/**
 * CommandBuilder provides basic methods to create query commands for SQLite tables.
 */
class CommandBuilder extends \DreamFactory\Core\Database\CommandBuilder
{
    /**
     * Generates the expression for selecting rows with specified composite key values.
     * This method is overridden because SQLite does not support the default
     * IN expression with composite columns.
     *
     * @param TableSchema $table  the table schema
     * @param array       $values list of primary key values to be selected within
     * @param string      $prefix column prefix (ended with dot)
     *
     * @return string the expression for selection
     */
    protected function createCompositeInCondition($table, $values, $prefix)
    {
        $keyNames = array();
        foreach (array_keys($values[0]) as $name) {
            $keyNames[] = $prefix . $table->columns[$name]->rawName;
        }
        $vs = array();
        foreach ($values as $value) {
            $vs[] = implode("||','||", $value);
        }

        return implode("||','||", $keyNames) . ' IN (' . implode(', ', $vs) . ')';
    }

    /**
     * Creates a multiple INSERT command.
     * This method could be used to achieve better performance during insertion of the large
     * amount of data into the database tables.
     * Note that SQLite does not keep original order of the inserted rows.
     *
     * @param mixed   $table the table schema ({@link TableSchema}) or the table name (string).
     * @param array[] $data  list data to be inserted, each value should be an array in format (column name=>column
     *                       value). If a key is not a valid column name, the corresponding value will be ignored.
     *
     * @return Command multiple insert command
     * @since 1.1.14
     */
    public function createMultipleInsertCommand($table, array $data)
    {
        $templates = array(
            'main'                  => 'INSERT INTO {{tableName}} ({{columnInsertNames}}) {{rowInsertValues}}',
            'columnInsertValue'     => '{{value}} AS {{column}}',
            'columnInsertValueGlue' => ', ',
            'rowInsertValue'        => 'SELECT {{columnInsertValues}}',
            'rowInsertValueGlue'    => ' UNION ',
            'columnInsertNameGlue'  => ', ',
        );

        return $this->composeMultipleInsertCommand($table, $data, $templates);
    }
}
