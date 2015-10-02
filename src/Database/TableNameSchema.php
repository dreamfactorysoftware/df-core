<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Library\Utility\Inflector;

/**
 * TableNameSchema is the base class for representing the metadata of a database table.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific table metadata.
 *
 * TableNameSchema provides the following information about a table:
 * <ul>
 * <li>{@link name}</li>
 * <li>{@link rawName}</li>
 * </ul>
 *
 * @property array $columnNames List of column names.
 */
class TableNameSchema
{
    /**
     * @var string name of this table.
     */
    public $name;
    /**
     * @var string Optional alias for this table. This alias can be used in the API to access the table.
     */
    public $alias;
    /**
     * @var string Optional label for this table.
     */
    public $label;
    /**
     * @var string Optional plural form of the label for of this table.
     */
    public $plural;
    /**
     * @var string Optional public description of this table.
     */
    public $description;
    /**
     * @var boolean Table or View?.
     */
    public $isView = false;

    public function __construct($name, $is_view = false)
    {
        $this->name = $name;
        $this->isView = $is_view;
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
        return (empty($this->label)) ? Inflector::camelize($this->getName(true), '_', true) : $this->label;
    }

    public function getPlural()
    {
        return (empty($this->plural)) ? Inflector::pluralize($this->getLabel()) : $this->plural;
    }

    public function toArray($use_alias = false)
    {
        $out = [
            'name'        => $this->getName($use_alias),
            'is_view'     => $this->isView,
            'label'       => $this->getLabel(),
            'plural'      => $this->getPlural(),
            'description' => $this->description,
        ];

        if (!$use_alias) {
            $out['alias'] = $this->alias;
        }

        return $out;
    }
}
