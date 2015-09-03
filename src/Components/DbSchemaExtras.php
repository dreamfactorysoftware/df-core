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

        $result = DbTableExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->get()->toArray();

        if ($include_fields) {
            $fieldResult =
                DbFieldExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->get()->toArray();
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
    public function getSchemaExtrasForFields($table_name, $field_names = '*', $select = '*')
    {
        if (empty($field_names)) {
            return [];
        }

        if ('*' === $field_names) {
            return DbFieldExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)
                ->get()
                ->toArray();
        }

        if (false === $values = DbUtilities::validateAsArray($field_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
        }

        return DbFieldExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)
                ->whereIn('field', $values)
                ->get()
                ->toArray();
    }

    /**
     * @param array $extras
     *
     * @return void
     */
    public function setSchemaTableExtras($extras)
    {
        if (empty($extras)) {
            return;
        }

        foreach ($extras as $extra) {
            if (!empty($table = ArrayUtils::get($extra, 'table'))) {
                DbTableExtras::updateOrCreate(['service_id' => $this->getServiceId(), 'table' => $table],
                    array_only($extra, ['alias', 'label', 'plural', 'description', 'name_field']));
            }
        }
    }

    /**
     * @param array $extras
     *
     * @return void
     */
    public function setSchemaFieldExtras($extras)
    {
        if (empty($extras)) {
            return;
        }

        foreach ($extras as $extra) {
            if (!empty($table = ArrayUtils::get($extra, 'table')) &&
                !empty($field = ArrayUtils::get($extra, 'field'))
            ) {
                DbFieldExtras::updateOrCreate([
                    'service_id' => $this->getServiceId(),
                    'table'      => $table,
                    'field'      => $field
                ], array_only($extra,
                    ['alias', 'label', 'extra_type', 'description', 'picklist', 'validation', 'client_info']));
            }
        }
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
            DbTableExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
            DbFieldExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
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
            DbFieldExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)
                ->whereIn('field', $values)
                ->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete DB Schema Extras. ' . $ex->getMessage());
        }
    }
}
