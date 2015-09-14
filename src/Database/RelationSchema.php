<?php
namespace DreamFactory\Core\Database;

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
     * @var string name of this relationship.
     */
    public $name;
    /**
     * @var string the DreamFactory simple type of this relationship.
     */
    public $type;
    /**
     * @var string the referenced table name
     */
    public $refTable;
    /**
     * @var string the referenced fields of the referenced table
     */
    public $refFields;
    /**
     * @var string the local table's field that is the foreign key
     */
    public $field;
    /**
     * @var string details the pivot or junction table
     */
    public $join;

    public function __construct($type, $ref_table, $ref_field, $field, $join = null)
    {
        switch ($type) {
            case static::BELONGS_TO:
                $name = $ref_table . '_by_' . $field;
                break;
            case static::HAS_MANY:
                $name = $ref_table . '_by_' . $ref_field;
                break;
            case static::MANY_MANY:
                $name = $ref_table . '_by_' . substr($join, 0, strpos($join, '('));
                break;
            default:
                $name = null;
                break;
        }

        $this->name = $name;
        $this->type = $type;
        $this->refTable = $ref_table;
        $this->refFields = $ref_field;
        $this->field = $field;
        $this->join = $join;
    }

    public function toArray()
    {
        return [
            'name'       => $this->name,
            'type'       => $this->type,
            'ref_table'  => $this->refTable,
            'ref_fields' => $this->refFields,
            'field'      => $this->field,
            'join'       => $this->join,
        ];
    }
}
