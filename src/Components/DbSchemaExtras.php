<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Utility\DbUtilities;
use Log;
use DreamFactory\Core\Models\DbFieldExtras;
use DreamFactory\Core\Models\DbTableExtras;
use DreamFactory\Library\Utility\ArrayUtils;

/**
 * DbSchemaExtras
 * Generic database table and field schema extras
 */
trait DbSchemaExtras
{
    /**
     * @param string | array $table_names
     * @param bool           $include_fields
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public function getSchemaExtrasForTables($table_names, $include_fields = true, $select = '*')
    {
        if (empty($table_names)) {
            return [];
        }

        if (false === $values = DbUtilities::validateAsArray($table_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid table list provided.');
        }

        $result = DbTableExtras::where('service_id', $this->id)->whereIn('table', $values)->get()->toArray();

        if ($include_fields) {
            $fieldResult = DbFieldExtras::where('service_id', $this->id)->whereIn('table', $values)->get()->toArray();
            $result = array_merge($result, $fieldResult);
        }

        return $result;
    }

    /**
     * @param string         $table_name
     * @param string | array $field_names
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public function getSchemaExtrasForFields($table_name, $field_names, $select = '*')
    {
        if (empty($field_names)) {
            return [];
        }

        if (false === $values = DbUtilities::validateAsArray($field_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
        }

        $results =
            DbFieldExtras::where('service_id', $this->id)
                ->where('table', $table_name)
                ->whereIn('field', $values)
                ->get()
                ->toArray();

        return $results;
    }

    /**
     * @param array $labels
     *
     * @return void
     */
    public function setSchemaExtras($labels)
    {
        if (empty($labels)) {
            return;
        }

        $tables = [];
        foreach ($labels as $label) {
            $tables[] = ArrayUtils::get($label, 'table');
        }

        $tables = array_unique($tables);
        $oldRows = static::getSchemaExtrasForTables($this->id, $tables);

        $inserts = $updates = [];

        foreach ($labels as $label) {
            $table = ArrayUtils::get($label, 'table');
            $field = ArrayUtils::get($label, 'field');
            $id = null;
            foreach ($oldRows as $row) {
                if ((ArrayUtils::get($row, 'table') == $table) && (ArrayUtils::get($row, 'field') == $field)) {
                    $id = ArrayUtils::get($row, 'id');
                }
            }

            if (empty($id)) {
                $inserts[] = $label;
            } else {
                $updates[$id] = $label;
            }
        }

//            $transaction = null;
//
//            try {
//                $transaction = $db->beginTransaction();
//            } catch (\Exception $ex) {
//                //	No transaction support
//                $transaction = false;
//            }
//
//            try {
//                $command = new \Command($db);
//
//                if (!empty($inserts)) {
//                    foreach ($inserts as $insert) {
//                        $command->reset();
//                        $insert['service_id'] = $this->id;
//                        $command->insert('df_sys_schema_extras', $insert);
//                    }
//                }
//
//                if (!empty($updates)) {
//                    foreach ($updates as $id => $update) {
//                        $command->reset();
//                        $update['service_id'] = $this->id;
//                        $command->update('df_sys_schema_extras', $update, 'id = :id', [':id' => $id]);
//                    }
//                }
//
//                if ($transaction) {
//                    $transaction->commit();
//                }
//            } catch (\Exception $ex) {
//                Log::error('Exception storing schema updates: ' . $ex->getMessage());
//
//                if ($transaction) {
//                    $transaction->rollback();
//                }
//            }
    }

    /**
     * @param string | array $table_names
     *
     */
    public function removeSchemaExtrasForTables($table_names)
    {
        if (false === $values = DbUtilities::validateAsArray($table_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid table list. ' . $table_names);
        }

        try {
            DbTableExtras::whereServiceId($this->id)->whereIn('table', $values)->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete from DB Schema Extras. ' . $ex->getMessage());
        }
    }

    /**
     * @param string         $table_name
     * @param string | array $field_names
     */
    public function removeSchemaExtrasForFields($table_name, $field_names)
    {
        if (false === $values = DbUtilities::validateAsArray($field_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
        }

        try {
            DbFieldExtras::whereServiceId($this->id)->whereTable($table_name)->whereIn('field', $values)->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete DB Schema Extras. ' . $ex->getMessage());
        }
    }
}
