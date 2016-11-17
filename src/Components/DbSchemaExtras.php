<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\DbFieldExtras;
use DreamFactory\Core\Models\DbRelationshipExtras;
use DreamFactory\Core\Models\DbTableExtras;
use DreamFactory\Core\Models\DbVirtualRelationship;
use Log;

/**
 * DbSchemaExtras
 * Generic database table and field schema extras
 */
trait DbSchemaExtras
{
    use DataValidator;

    /**
     * @param string | array $table_names
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public function getSchemaExtrasForTables($table_names, $select = '*')
    {
        if (empty($table_names)) {
            return [];
        }

        if (false === $values = static::validateAsArray($table_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid table list provided.');
        }

        $result = DbTableExtras::whereServiceId($this->getServiceId())
            ->whereIn('table', $values)->get((array)$select)->toArray();

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
                ->whereTable($table_name)->get()->toArray();
        } else {
            if (false === $values = static::validateAsArray($field_names, ',', true)) {
                throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
            }

            $result = DbFieldExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)->whereIn('field', $values)->get((array)$select)->toArray();
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
            return DbRelationshipExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)->get((array)$select)->toArray();
        }

        if (false === $values = static::validateAsArray($related_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid related list. ' . $related_names);
        }

        return DbRelationshipExtras::whereServiceId($this->getServiceId())
            ->whereTable($table_name)->whereIn('name', $values)->get((array)$select)->toArray();
    }

    /**
     * @param string         $table_name
     * @param string | array $select
     *
     * @throws \InvalidArgumentException
     * @return array
     */
    public function getSchemaVirtualRelationships($table_name, $select = '*')
    {
        return DbVirtualRelationship::whereServiceId($this->getServiceId())
            ->whereTable($table_name)->get((array)$select)->toArray();
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
            if (!empty($table = array_get($extra, 'table'))) {
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
            if (!empty($table = array_get($extra, 'table')) &&
                !empty($field = array_get($extra, 'field'))
            ) {
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
            if (!empty($table = array_get($extra, 'table')) &&
                !empty($name = array_get($extra, 'relationship'))
            ) {
                DbRelationshipExtras::updateOrCreate(
                    [
                        'service_id'   => $this->getServiceId(),
                        'table'        => $table,
                        'relationship' => $name
                    ],
                    array_only($extra,
                        [
                            'alias',
                            'label',
                            'description',
                            'always_fetch',
                            'flatten',
                            'flatten_drop_prefix',
                        ]
                    )
                );
            }
        }
    }

    /**
     * @param array $relationships
     *
     * @return void
     */
    public function setSchemaVirtualRelationships($relationships)
    {
        if (empty($relationships)) {
            return;
        }

        foreach ($relationships as $extra) {
            if (!empty($extra['ref_table']) && empty($extra['ref_service_id'])) {
                // don't allow empty ref_service_id into the database, needs to be searchable from other services
                $extra['ref_service_id'] = $this->getServiceId();
            }
            if (!empty($extra['junction_table']) && empty($extra['junction_service_id'])) {
                // don't allow empty junction_service_id into the database, needs to be searchable from other services
                $extra['junction_service_id'] = $this->getServiceId();
            }
            DbVirtualRelationship::updateOrCreate(
                [
                    'type'                => array_get($extra, 'type'),
                    'service_id'          => $this->getServiceId(),
                    'table'               => array_get($extra, 'table'),
                    'field'               => array_get($extra, 'field'),
                    'ref_service_id'      => array_get($extra, 'ref_service_id'),
                    'ref_table'           => array_get($extra, 'ref_table'),
                    'ref_field'           => array_get($extra, 'ref_field'),
                    'junction_service_id' => array_get($extra, 'junction_service_id'),
                    'junction_table'      => array_get($extra, 'junction_table'),
                    'junction_field'      => array_get($extra, 'junction_field'),
                    'junction_ref_field'  => array_get($extra, 'junction_ref_field'),
                ],
                array_only($extra,
                    [
                        'ref_on_update',
                        'ref_on_delete',
                    ]
                )
            );
        }
    }

    /**
     * @param string | array $table_names
     *
     */
    public function removeSchemaExtrasForTables($table_names)
    {
        if (false === $values = static::validateAsArray($table_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid table list. ' . $table_names);
        }

        try {
            DbTableExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
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
        if (false === $values = static::validateAsArray($field_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
        }

        try {
            DbFieldExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)->whereIn('field', $values)->delete();
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
        if (false === $values = static::validateAsArray($related_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid related list. ' . $related_names);
        }

        try {
            DbRelationshipExtras::whereServiceId($this->getServiceId())
                ->whereTable($table_name)->whereIn('relationship', $values)->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete DB Related Schema Extras. ' . $ex->getMessage());
        }
    }

    /**
     * @param string         $table_name
     * @param string | array $relationships
     */
    public function removeSchemaVirtualRelationships($table_name, $relationships)
    {
        if (empty($relationships)) {
            return;
        }

        foreach ($relationships as $extra) {
            if (!empty($extra['ref_table']) && empty($extra['ref_service_id'])) {
                // don't allow empty ref_service_id into the database, needs to be searchable from other services
                $extra['ref_service_id'] = $this->getServiceId();
            }
            if (!empty($extra['junction_table']) && empty($extra['junction_service_id'])) {
                // don't allow empty junction_service_id into the database, needs to be searchable from other services
                $extra['junction_service_id'] = $this->getServiceId();
            }
            DbVirtualRelationship::whereType(array_get($extra, 'type'))->whereServiceId($this->getServiceId())
                ->whereTable($table_name)->whereField(array_get($extra, 'field'))
                ->whereRefServiceId(array_get($extra, 'ref_service_id'))
                ->whereRefTable(array_get($extra, 'ref_table'))->whereRefField(array_get($extra, 'ref_field'))
                ->whereJunctionServiceId(array_get($extra, 'junction_service_id'))
                ->whereJunctionTable(array_get($extra, 'junction_table'))
                ->whereJunctionField(array_get($extra, 'junction_field'))
                ->whereJunctionRefField(array_get($extra, 'junction_ref_field'))
                ->delete();
        }
    }

    /**
     * @param string $table_names
     */
    public function tablesDropped($table_names)
    {
        if (false === $values = static::validateAsArray($table_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid table list. ' . $table_names);
        }

        try {
            DbTableExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
            DbFieldExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
            DbRelationshipExtras::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
            DbVirtualRelationship::whereServiceId($this->getServiceId())->whereIn('table', $values)->delete();
            DbVirtualRelationship::whereRefServiceId($this->getServiceId())->whereIn('ref_table', $values)->delete();
            DbVirtualRelationship::whereJunctionServiceId($this->getServiceId())
                ->whereIn('junction_table', $values)->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete from DB Table Schema Extras. ' . $ex->getMessage());
        }
    }

    /**
     * @param string $table_name
     * @param string $field_names
     */
    public function fieldsDropped($table_name, $field_names)
    {
        if (false === $values = static::validateAsArray($field_names, ',', true)) {
            throw new \InvalidArgumentException('Invalid field list. ' . $field_names);
        }

        try {
            DbFieldExtras::whereServiceId($this->getServiceId())->whereTable($table_name)
                ->whereIn('field', $values)->delete();
//            DbRelationshipExtras::whereServiceId($this->getServiceId())->whereTable($table_name)->whereIn('field', $values)->delete();
            DbVirtualRelationship::whereServiceId($this->getServiceId())->whereTable($table_name)
                ->whereIn('field', $values)->delete();
            DbVirtualRelationship::whereRefServiceId($this->getServiceId())->whereRefTable($table_name)
                ->whereIn('ref_field', $values)->delete();
            DbVirtualRelationship::whereJunctionServiceId($this->getServiceId())->whereJunctionTable($table_name)
                ->whereIn('junction_field', $values)->delete();
            DbVirtualRelationship::whereJunctionServiceId($this->getServiceId())->whereJunctionTable($table_name)
                ->whereIn('junction_ref_field', $values)->delete();
        } catch (\Exception $ex) {
            Log::error('Failed to delete from DB Field Schema Extras. ' . $ex->getMessage());
        }
    }
}
