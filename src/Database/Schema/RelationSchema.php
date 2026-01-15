<?php

namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Core\Enums\DbSimpleTypes;

/**
 * RelationSchema class describes the relationship meta data of a database table.
 */
class RelationSchema extends NamedResourceSchema
{
    /**
     * The followings are the supported abstract relationship types.
     */
    /**
     * @var string Represents that this table is related to another by a local reference/foreign key field, reverse of
     *      has_one or has_many
     */
    const BELONGS_TO = 'belongs_to';
    /**
     * @var string Represents that another table has a reference/foreign key field linked to this table,
     *      also acting as a unique key or primary key so that there is only one record allowed, reverse of belongs_to
     */
    const HAS_ONE = 'has_one';
    /**
     * @var string Represents that another table has a reference/foreign key field linked to this table, reverse of
     *      belongs_to
     */
    const HAS_MANY = 'has_many';
    /**
     * @var string Represents that this table is linked to another table via a pivot/junction table.
     */
    const MANY_MANY = 'many_many';

    /**
     * @var array List of extra information fields.
     */
    public static $extraFields = ['label', 'description', 'always_fetch', 'flatten', 'flatten_drop_prefix'];

    /**
     * @var boolean Fetch this relationship whenever the parent is fetched.
     */
    public $alwaysFetch = false;
    /**
     * @var boolean Flatten the fields to the parent if a single record and possible.
     */
    public $flatten = false;
    /**
     * @var boolean If flattened, do we drop the prefix, i.e. the relationship name.
     *              Note: field names of child record must be unique, otherwise conflicts may arise.
     */
    public $flattenDropPrefix = false;
    /**
     * @var string the DreamFactory simple type of this relationship.
     */
    public $type;
    /**
     * @var boolean Is this a virtual reference.
     */
    public $isVirtual = false;
    /**
     * @var array The local table's fields that make up the foreign key
     */
    public $field;
    /**
     * @var integer|null The referenced service id
     */
    public $refServiceId;
    /**
     * @var string The referenced table name
     */
    public $refTable;
    /**
     * @var array The referenced fields of the referenced table
     */
    public $refField;
    /**
     * @var string if a foreign key, then what to do with this field's value when the foreign is updated
     */
    public $refOnUpdate;
    /**
     * @var string if a foreign key, then what to do with this field's value when the foreign is deleted
     */
    public $refOnDelete;
    /**
     * @var integer details the service id of the pivot or junction table
     */
    public $junctionServiceId;
    /**
     * @var string details the pivot or junction table
     */
    public $junctionTable;
    /**
     * @var array details the pivot or junction table fields facing the native
     */
    public $junctionField;
    /**
     * @var array details the pivot or junction table fields facing the foreign
     */
    public $junctionRefField;

    /**
     * @param array $settings
     * @return string
     * @throws \Exception
     */
    public static function buildName(array $settings)
    {
        if (empty($refTable = array_get($settings, 'ref_table'))) {
            throw new \Exception('Failed to build relationship. Missing referenced table.');
        }
        if (!empty($refService = array_get($settings, 'ref_service'))) {
            $refTable = $refService . '.' . $refTable;
        }
        switch ($type = array_get($settings, 'type')) {
            case static::BELONGS_TO:
                if (empty($field = array_get($settings, 'field'))) {
                    throw new \Exception('Failed to build relationship. Missing referencing fields.');
                }
                if (is_array($field)) {
                    $field = implode('_', $field);
                }

                return $refTable . '_by_' . $field;
            case static::HAS_ONE:
            case static::HAS_MANY:
                if (empty($refField = array_get($settings, 'ref_field'))) {
                    throw new \Exception('Failed to build relationship. Missing referenced fields.');
                }
                if (is_array($refField)) {
                    $refField = implode('_', $refField);
                }

                return $refTable . '_by_' . $refField;
            case static::MANY_MANY:
                if (empty($junctionTable = array_get($settings, 'junction_table'))) {
                    throw new \Exception('Failed to build relationship. Missing referenced junction table.');
                }
                if (!empty($junctionService = array_get($settings, 'junction_service'))) {
                    $junctionTable = $junctionService . '.' . $junctionTable;
                }

                return $refTable . '_by_' . $junctionTable;
            default:
                throw new \Exception('Failed to build relationship. Invalid relationship type: ' . $type);
        }
    }

    /**
     * RelationSchema constructor.
     * @param array $settings
     * @throws \Exception
     */
    public function __construct(array $settings)
    {
        parent::__construct($settings);

        if (isset($this->field) && !is_array($this->field)) {
            /** @noinspection PhpParamsInspection */
            $this->field = explode(',', $this->field);
        }
        if (isset($this->refField) && !is_array($this->refField)) {
            /** @noinspection PhpParamsInspection */
            $this->refField = explode(',', $this->refField);
        }
        if (isset($this->junctionField) && !is_array($this->junctionField)) {
            /** @noinspection PhpParamsInspection */
            $this->junctionField = explode(',', $this->junctionField);
        }
        if (isset($this->junctionRefField) && !is_array($this->junctionRefField)) {
            /** @noinspection PhpParamsInspection */
            $this->junctionRefField = explode(',', $this->junctionRefField);
        }

        if (empty($this->name)) {
            $this->setName(static::buildName($settings));
        }
    }

    public function toArray($use_alias = false)
    {
        $out = [
            'type'                => $this->type,
            'field'               => (is_array($this->field) ? implode(',', $this->field) : $this->field),
            'is_virtual'          => $this->isVirtual,
            'ref_service_id'      => $this->refServiceId,
            'ref_table'           => $this->refTable,
            'ref_field'           => (is_array($this->refField) ? implode(',', $this->refField) : $this->refField),
            'ref_on_update'       => $this->refOnUpdate,
            'ref_on_delete'       => $this->refOnDelete,
            'junction_service_id' => $this->junctionServiceId,
            'junction_table'      => $this->junctionTable,
            'junction_field'      => (is_array($this->junctionField) ? implode(',',
                $this->junctionField) : $this->junctionField),
            'junction_ref_field'  => (is_array($this->junctionRefField) ? implode(',',
                $this->junctionRefField) : $this->junctionRefField),
            'always_fetch'        => $this->alwaysFetch,
            'flatten'             => $this->flatten,
            'flatten_drop_prefix' => $this->flattenDropPrefix,
        ];

        return array_merge(parent::toArray($use_alias), $out);
    }

    public static function getSchema()
    {
        return [
            'name'        => 'db_schema_table_relation',
            'description' => 'The database table relation schema.',
            'type'        => DbSimpleTypes::TYPE_OBJECT,
            'properties'  => [
                'name'               => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Name of the relationship.',
                ],
                'alias'              => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Alias to use in the API to override the name the relationship.',
                ],
                'label'              => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Label for the relationship.',
                ],
                'description'        => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Description of the relationship.',
                ],
                'type'               => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Relationship type - belongs_to, has_many, many_many.',
                ],
                'field'              => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The current table field that is used in the relationship.',
                ],
                'ref_table'          => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The table name that is referenced by the relationship.',
                ],
                'ref_field'          => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The field name that is referenced by the relationship.',
                ],
                'junction_table'     => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The intermediate junction table used for many_many relationships.',
                ],
                'junction_field'     => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The intermediate junction table field used for many_many relationships.',
                ],
                'junction_ref_field' => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'The intermediate joining table referencing field used for many_many relationships.',
                ],
                'always_fetch'       => [
                    'type'        => DbSimpleTypes::TYPE_BOOLEAN,
                    'description' => 'Always fetch this relationship when querying the parent table.',
                ],
            ]
        ];
    }
}
