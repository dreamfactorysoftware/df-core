<?php
namespace DreamFactory\Core\Database\Ibmdb2;

use DreamFactory\Core\Database\TableSchema;
use DreamFactory\Core\Database\Criteria;
use DreamFactory\Core\Database\Command;

/**
 * CommandBuilder provides basic methods to create query commands for tables for IBM DB2 Servers.
 */
class CommandBuilder extends \DreamFactory\Core\Database\CommandBuilder
{

    /**
     * Alters the SQL to apply LIMIT and OFFSET.
     * Default implementation is applicable for PostgreSQL, MySQL and SQLite.
     *
     * @param string  $sql    SQL query string without LIMIT and OFFSET.
     * @param integer $limit  maximum number of rows, -1 to ignore limit.
     * @param integer $offset row offset, -1 to ignore offset.
     *
     * @return string SQL with LIMIT and OFFSET
     */
    public function applyLimit($sql, $limit, $offset)
    {
        $limit = $limit !== null ? (int)$limit : 0;
        $offset = $offset !== null ? (int)$offset : 0;

        if ($limit > 0 && $offset <= 0) {
            $sql .= ' FETCH FIRST ' . $limit . ' ROWS ONLY';
        } elseif ($offset > 0) {
            $query = 'SELECT dbnumberedrows.* FROM (
    SELECT ROW_NUMBER() OVER() AS dbrownumber, dbresult.* FROM (
        ' . $sql . ' FETCH FIRST ' . ($offset + $limit) . ' ROWS ONLY
    ) AS dbresult
) AS dbnumberedrows';
            if ($limit == 1) {
                $query .= ' WHERE (dbnumberedrows.dbrownumber = ' . ($offset + 1) . ')';
            } elseif ($limit > 0) {
                $query .=
                    ' WHERE (dbnumberedrows.dbrownumber BETWEEN ' .
                    ($offset + 1) .
                    ' AND ' .
                    ($offset + $limit) .
                    ')';
            } else {
                $query .= ' WHERE (dbnumberedrows.dbrownumber > ' . ($offset + 1) . ')';
            }

            return $query;
        }

        return $sql;
    }

    /**
     * Creates a COUNT(*) command for a single table.
     *
     * @param mixed    $table    the table schema ({@link TableSchema}) or the table name (string).
     * @param Criteria $criteria the query criteria
     * @param string   $alias    the alias name of the primary table. Defaults to 't'.
     *
     * @return Command query command.
     */
    public function createCountCommand($table, $criteria, $alias = 't')
    {
        $table_clone = clone $table;
        if (is_array($table->primaryKey)) {
            foreach ($table->primaryKey as $pos => $pk) {
                $table_clone->primaryKey[$pos] = $this->getSchema()->quoteColumnName($pk);
            }
        } else {
            $table_clone->primaryKey = $this->getSchema()->quoteColumnName($table->primaryKey);
        }

        return parent::createCountCommand($table_clone, $criteria, $alias);
    }

    /**
     * Creates an UPDATE command.
     *
     * @param mixed    $table    the table schema ({@link TableSchema}) or the table name (string).
     * @param array    $data     list of columns to be updated (name=>value)
     * @param Criteria $criteria the query criteria
     *
     * @throws \Exception if no columns are being updated for the given table
     * @return Command update command.
     */
    public function createUpdateCommand($table, $data, $criteria)
    {
        foreach ($data as $name => $value) {
            if (($column = $table->getColumn($name)) !== null) {
                if ($column->autoIncrement) {
                    unset($data[$name]);
                    continue;
                }
            }
        }

        return parent::createUpdateCommand($table, $data, $criteria);
    }

    /**
     * Generates the expression for selecting rows with specified composite key values.
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
            $vs[] = '(' . implode(', ', $value) . ')';
        }

        return '(' . implode(', ', $keyNames) . ') IN (VALUES ' . implode(', ', $vs) . ')';
    }
}
