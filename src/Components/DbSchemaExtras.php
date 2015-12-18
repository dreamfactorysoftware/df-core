<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\Service;
use Log;
use DreamFactory\Core\Models\DbFieldExtras;
use DreamFactory\Core\Models\DbRelatedExtras;
use DreamFactory\Core\Models\DbTableExtras;
use DreamFactory\Core\Utility\DbUtilities;
use DreamFactory\Library\Utility\ArrayUtils;

/**
 * DbSchemaExtras
 * Generic database table and field schema extras
 */
trait DbSchemaExtras
{
    /**
     * @param string | array $table_names
     * @param bool           $include_all
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public function getSchemaExtrasForTables($table_names, $include_all = true, $select = '*')
    {
        if (empty($table_names)) {
            return [];
        }

        if (false === $values = DbUtilities::validateAsArray($table_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid table list provided.');
        }

        $result = DbTableExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->get()->toArray();

        if ($include_all) {
            $fieldResult =
                DbFieldExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->get()->toArray();

            $relatedResult =
                DbRelatedExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->get()->toArray();
            $result = array_merge($result, $fieldResult, $relatedResult);
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
            $result = DbFieldExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)
                ->get()
                ->toArray();
        } else {

            if (false === $values = DbUtilities::validateAsArray($field_names, ',', true)) {
                throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
            }

            $result = DbFieldExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)
                ->whereIn('field', $values)
                ->get()
                ->toArray();
        }

        foreach ($result as &$extra) {
            if (!empty($extra['ref_service_id']) && ($extra['ref_service_id'] != $extra['service_id'])) {
                $extra['ref_service'] = Service::getCachedNameById($extra['ref_service_id']);
            }
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
    public function getSchemaExtrasForFieldsReferenced($table_name, $field_names = '*', $select = '*')
    {
        if (empty($field_names)) {
            return [];
        }

        if ('*' === $field_names) {
            $result = DbFieldExtras::whereRefServiceId($this->getServiceId())
                ->whereRefTable($table_name)
                ->get()
                ->toArray();
        } else {
            if (false === $values = DbUtilities::validateAsArray($field_names, ',', true)) {
                throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
            }

            $result = DbFieldExtras::whereRefServiceId($this->getServiceId())
                ->whereRefTable($table_name)
                ->whereIn('ref_fields', $values)
                ->get()
                ->toArray();
        }

        foreach ($result as &$extra) {
            if (!empty($extra['ref_service_id']) && ($extra['ref_service_id'] != $extra['service_id'])) {
                $extra['service'] = Service::getCachedNameById($extra['service_id']);
            }
        }

        return $result;
    }

    /**
     * @param string         $table_name
     * @param string | array $related_names
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public function getSchemaExtrasForRelated($table_name, $related_names = '*', $select = '*')
    {
        if (empty($related_names)) {
            return [];
        }

        if ('*' === $related_names) {
            return DbRelatedExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)
                ->get()
                ->toArray();
        }

        if (false === $values = DbUtilities::validateAsArray($related_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid related list. ' . $related_names);
        }

        return DbRelatedExtras::whereServiceId($this->getServiceId())
            ->whereTable($table_name)
            ->whereIn('relationship', $values)
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
                if (!empty($extra['ref_table']) && empty($extra['ref_service_id'])) {
                    if (!empty($extra['ref_service'])) {
                        // translate name to id for storage
                        $extra['ref_service_id'] =
                            Service::getCachedByName($extra['ref_service'], 'id', $this->getServiceId());
                    } else {
                        // don't allow empty ref_service_id into the database, needs to be searchable from other services
                        $extras['ref_service_id'] = $this->getServiceId();
                    }
                }
                DbFieldExtras::updateOrCreate([
                    'service_id' => $this->getServiceId(),
                    'table'      => $table,
                    'field'      => $field
                ], array_only($extra,
                    [
                        'alias',
                        'label',
                        'extra_type',
                        'description',
                        'picklist',
                        'validation',
                        'client_info',
                        'db_function',
                        'ref_service_id',
                        'ref_table',
                        'ref_fields',
                        'ref_on_update',
                        'ref_on_delete',
                    ]));
            }
        }
    }

    /**
     * @param array $extras
     *
     * @return void
     */
    public function setSchemaRelatedExtras($extras)
    {
        if (empty($extras)) {
            return;
        }

        foreach ($extras as $extra) {
            if (!empty($table = ArrayUtils::get($extra, 'table')) &&
                !empty($relationship = ArrayUtils::get($extra, 'relationship'))
            ) {
                DbRelatedExtras::updateOrCreate([
                    'service_id'   => $this->getServiceId(),
                    'table'        => $table,
                    'relationship' => $relationship
                ], array_only($extra,
                    [
                        'alias',
                        'label',
                        'description',
                        'always_fetch',
                        'flatten',
                        'flatten_drop_prefix',
                    ]));
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
            DbRelatedExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete from DB Table Schema Extras. ' . $ex->getMessage());
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
            Log::error('Failed to delete DB Field Schema Extras. ' . $ex->getMessage());
        }
    }

    /**
     * @param string         $table_name
     * @param string | array $related_names
     */
    public function removeSchemaExtrasForRelated($table_name, $related_names)
    {
        if (false === $values = DbUtilities::validateAsArray($related_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid related list. ' . $related_names);
        }

        try {
            DbRelatedExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)
                ->whereIn('relationship', $values)
                ->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete DB Related Schema Extras. ' . $ex->getMessage());
        }
    }
}
