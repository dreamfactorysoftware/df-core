<?php
namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Library\Utility\Inflector;

/**
 * RelationSchema class describes the relationship meta data of a database table.
 */
class RelationSchema
{
    /**
     * The followings are the supported abstract relationship types.
     */
    /**
     * @var string Represents that this table is related to another by a local reference/foreign key field, reverse of
     *      has_many
     */
    const BELONGS_TO = 'belongs_to';
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
     * @var string Auto-generated name of this relationship.
     */
    public $name;
    /**
     * @var string Optional alias for this relationship.
     */
    public $alias;
    /**
     * @var string Optional label for this relationship.
     */
    public $label;
    /**
     * @var string Optional description for this relationship.
     */
    public $description;
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
     * @var string The local table's field that is the foreign key
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
     * @var string The referenced fields of the referenced table
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
     * @var string details the pivot or junction table field facing the native
     */
    public $junctionField;
    /**
     * @var string details the pivot or junction table field facing the foreign
     */
    public $junctionRefField;

    public static function buildName(
        $type,
        $field,
        $refService,
        $refTable,
        $refField,
        $junctionService = null,
        $junctionTable = null
    ) {
        $table = $refTable;
        if (!empty($refService)) {
            $table = $refService . '.' . $table;
        }
        switch ($type) {
            case static::BELONGS_TO:
                return $table . '_by_' . $field;
            case static::HAS_MANY:
                return $table . '_by_' . $refField;
            case static::MANY_MANY:
                $junction = $junctionTable;
                if (!empty($junctionService)) {
                    $junction = $junctionService . '.' . $junction;
                }

                return $table . '_by_' . $junction;
            default:
                return null;
        }
    }

    public function __construct(array $settings)
    {
        $this->fill($settings);

        if (empty($this->name)) {
            $this->name = static::buildName($this->type, $this->field, null, $this->refTable,
                $this->refField, null, $this->junctionTable);
        }
    }

    public function fill(array $settings)
    {
        foreach ($settings as $key => $value) {
            if (!property_exists($this, $key)) {
                // try camel cased
                $camel = camel_case($key);
                if (property_exists($this, $camel)) {
                    $this->{$camel} = $value;
                    continue;
                }
            }
            // set real and virtual
            $this->{$key} = $value;
        }
    }

    public function getName($use_alias = false)
    {
        return ($use_alias && !empty($this->alias)) ? $this->alias : $this->name;
    }

    public function getLabel()
    {
        $name = str_replace('.', ' ', $this->getName(true));

        return (empty($this->label)) ? Inflector::camelize($name, '_', true) : $this->label;
    }

    public function toArray($use_alias = false)
    {
        $out = [
            'name'                => $this->getName($use_alias),
            'label'               => $this->getLabel(),
            'description'         => $this->description,
            'type'                => $this->type,
            'field'               => $this->field,
            'is_virtual'          => $this->isVirtual,
            'ref_service_id'      => $this->refServiceId,
            'ref_table'           => $this->refTable,
            'ref_field'           => $this->refField,
            'ref_on_update'       => $this->refOnUpdate,
            'ref_on_delete'       => $this->refOnDelete,
            'junction_service_id' => $this->junctionServiceId,
            'junction_table'      => $this->junctionTable,
            'junction_field'      => $this->junctionField,
            'junction_ref_field'  => $this->junctionRefField,
            'always_fetch'        => $this->alwaysFetch,
            'flatten'             => $this->flatten,
            'flatten_drop_prefix' => $this->flattenDropPrefix,
        ];

        if (!$use_alias) {
            $out = array_merge(['alias' => $this->alias], $out);
        }

        return $out;
    }
}
