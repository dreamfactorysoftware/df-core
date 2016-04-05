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
     * @var boolean Is this a virtual reference to a foreign service.
     */
    public $isForeignService = false;
    /**
     * @var string|null The referenced service name
     */
    public $refService;
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
    public $refFields;
    /**
     * @var string if a foreign key, then what to do with this field's value when the foreign is updated
     */
    public $refOnUpdate;
    /**
     * @var string if a foreign key, then what to do with this field's value when the foreign is deleted
     */
    public $refOnDelete;
    /**
     * @var string The local table's field that is the foreign key
     */
    public $field;
    /**
     * @var boolean Is this a junction table on a foreign service.
     */
    public $isForeignJunctionService = false;
    /**
     * @var string details the service name of the pivot or junction table
     */
    public $junctionService;
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

    public function __construct(array $settings)
    {
        $this->fill($settings);

        if (empty($this->name)) {
            $table = $this->refTable;
            if ($this->isForeignService && $this->refService) {
                $table = $this->refService . '.' . $table;
            }
            switch ($this->type) {
                case static::BELONGS_TO:
                    $this->name = $table . '_by_' . $this->field;
                    break;
                case static::HAS_MANY:
                    $this->name = $table . '_by_' . $this->refFields;
                    break;
                case static::MANY_MANY:
                    $junction = $this->junctionTable;
                    if ($this->isForeignJunctionService && $this->junctionService) {
                        $junction = $this->junctionService . '.' . $junction;
                    }
                    $this->name = $table . '_by_' . $junction;
                    break;
                default:
                    break;
            }
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
            'name'                        => $this->getName($use_alias),
            'label'                       => $this->getLabel(),
            'description'                 => $this->description,
            'always_fetch'                => $this->alwaysFetch,
            'flatten'                     => $this->flatten,
            'flatten_drop_prefix'         => $this->flattenDropPrefix,
            'type'                        => $this->type,
            'field'                       => $this->field,
            'is_virtual'                  => $this->isVirtual,
            'is_foreign_service'          => $this->isForeignService,
            'ref_service'                 => $this->refService,
            'ref_service_id'              => $this->refServiceId,
            'ref_table'                   => $this->refTable,
            'ref_fields'                  => $this->refFields,
            'ref_on_update'               => $this->refOnUpdate,
            'ref_on_delete'               => $this->refOnDelete,
            'is_foreign_junction_service' => $this->isForeignJunctionService,
            'junction_service'            => $this->junctionService,
            'junction_service_id'         => $this->junctionServiceId,
            'junction_table'              => $this->junctionTable,
            'junction_field'              => $this->junctionField,
            'junction_ref_field'          => $this->junctionRefField,
        ];

        if (!$use_alias) {
            $out = array_merge(['alias' => $this->alias], $out);
        }

        return $out;
    }
}
