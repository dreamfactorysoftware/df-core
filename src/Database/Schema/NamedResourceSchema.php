<?php
namespace DreamFactory\Core\Database\Schema;

use DreamFactory\Core\Contracts\NamedInstanceInterface;


/**
 * NamedResourceSchema is the base class for representing the metadata of a database resource.
 *
 * It may be extended by different DBMS driver to provide DBMS-specific resource metadata.
 *
 * NamedResourceSchema provides the following information about a resource:
 * <ul>
 * <li>{@link name}</li>
 * </ul>
 *
 */
class NamedResourceSchema implements NamedInstanceInterface
{
    /**
     * @var mixed Identifier of this resource (vendor specific).
     * Defaults to null, meaning no id given, must use schema and resource name to identify.
     */
    public $id;
    /**
     * @var string Name of the catalog (database) that this resource belongs to (SQL Server specific).
     * Defaults to null, meaning no catalog (or the current database).
     */
    public $catalogName;
    /**
     * @var string Name of the schema that this resource belongs to.
     */
    public $schemaName;
    /**
     * @var string Name of this resource without any additional schema declaration.
     */
    public $resourceName;
    /**
     * @var string Internal full name of this resource. This is the non-quoted version of resource name with schema name.
     * It can be directly used in SQL statements.
     */
    public $internalName;
    /**
     * @var string Quoted full name of this resource. This is the quoted version of resource name with schema name.
     * It can be directly used in SQL statements.
     */
    public $quotedName;
    /**
     * @var string Public name of this resource. This is the resource name with optional non-default schema name.
     * It is to be used by clients.
     */
    public $name;
    /**
     * @var string Optional alias for this resource. This alias can be used in the API to access the resource.
     */
    public $alias;
    /**
     * @var string Quoted alias for this resource. This is the quoted version of resource name with schema name.
     * It can be directly used in SQL statements. Primarily used for aliased column names.
     */
    public $quotedAlias;
    /**
     * @var string Optional label for this resource.
     */
    public $label;
    /**
     * @var string Optional public description of this resource.
     */
    public $description;
    /**
     * @var boolean Has the full schema been discovered, or just name and type.
     */
    public $discoveryCompleted = false;
    /**
     * @var array Any resource-specific information native to this platform.
     */
    public $native = [];
    /**
     * @var string Character sequence used to quote a resource for internal use.
     */
    public $leftQuoteCharacter = '"';
    /**
     * @var string Character sequence used to quote a resource for internal use.
     */
    public $rightQuoteCharacter = '"';


    public function __construct(array $settings)
    {
        $this->fill($settings);

        // fill out naming options if not present
        $this->setName($this->name);
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

    public function setName($name)
    {
        $this->name = $name;

        // fill out naming options if not present
        if (empty($this->resourceName)) {
            $this->resourceName = $this->name;
        }
        if (empty($this->internalName)) {
            $this->internalName = $this->name;
        }
        if (empty($this->quotedName)) {
            $this->quotedName = $this->name;
        }
    }

    /**
     * Quotes a resource name for use in a query.
     * If the resource name contains schema prefix, the prefix will also be properly quoted.
     *
     * @param string $name resource name
     *
     * @return string the properly quoted resource name
     */
    public function quoteName($name)
    {
        if (strpos($name, '.') === false) {
            return $this->quoteSimpleName($name);
        }
        $parts = explode('.', $name);
        foreach ($parts as $i => $part) {
            if ('*' !== $part) { // last part may be wildcard, i.e. select table.*
                $parts[$i] = $this->quoteSimpleName($part);
            }
        }

        return implode('.', $parts);
    }

    /**
     * Quotes a simple name for use in a query.
     * A simple column name does not contain prefix.
     *
     * @param string $name column name
     *
     * @return string the properly quoted column name
     */
    public function quoteSimpleName($name)
    {
        return $this->leftQuoteCharacter . $name . $this->rightQuoteCharacter;
    }

    public function getName($use_alias = false, $quoted = false)
    {
        if ($quoted) {
            return ($use_alias && !empty($this->alias)) ? $this->quotedAlias : $this->quotedName;
        }

        return ($use_alias && !empty($this->alias)) ? $this->alias : $this->name;
    }

    public function getLabel($use_alias = false)
    {
        return (empty($this->label)) ? camelize($this->getName($use_alias), '_', true) : $this->label;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function toArray($use_alias = false)
    {
        $out = [
            'name'        => $this->getName($use_alias),
            'label'       => $this->getLabel(),
            'description' => $this->description,
            'native'      => $this->native,
        ];

        if (!$use_alias) {
            $out = array_merge(['alias' => $this->alias], $out);
        }

        return $out;
    }
}
