<?php
namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\SystemResourceInterface;
use DreamFactory\Core\Contracts\SystemResourceTypeInterface;

/**
 * Interface SystemResourceType
 *
 * Something that defines a system resource type
 *
 * @package DreamFactory\Core\Contracts
 */
class SystemResourceType implements SystemResourceTypeInterface
{
    /**
     * @var string Designated type of a service
     */
    protected $name = '';
    /**
     * @var string Displayable label for this system resource type
     */
    protected $label = '';
    /**
     * @var string Description of this system resource type
     */
    protected $description = '';
    /**
     * @var boolean True if this system resource type should only be created once per instance
     */
    protected $singleton = false;
    /**
     * @var boolean True if this system resource type should only be created once per instance
     */
    protected $readOnly = false;
    /**
     * @var string Designated class for this system resource type, typically ties to database storage
     */
    protected $className = null;
    /**
     * @var callable Designated callback for creating a service of this type
     */
    protected $factory = null;

    /**
     * Create a new system resource type instance.
     *
     * @param array $settings
     */
    public function __construct($settings = [])
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

    /**
     * system resource type - matching registered system resource types
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Displayable system resource type label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * system resource type description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * system resource type class name
     *
     * @return string
     */
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * Is this system resource type only to be created once?
     *
     * @return boolean
     */
    public function isSingleton()
    {
        return $this->singleton;
    }

    /**
     * Is this system resource type read only?
     *
     * @return boolean
     */
    public function isReadOnly()
    {
        return $this->readOnly;
    }

    /**
     * The configuration handler interface for this system resource type
     *
     * @param string $name
     * @param array  $config
     *
     * @return SystemResourceInterface|null
     */
    public function make($name, array $config = [])
    {
        return call_user_func($this->factory, $config, $name);
    }

    /**
     * The configuration handler interface for this system resource type
     *
     * @return array | null
     */
    public function toArray()
    {
        return [
            'name'        => $this->name,
            'label'       => $this->label,
            'description' => $this->description,
            'class_name'  => $this->className,
            'singleton'   => $this->singleton,
            'read_only'   => $this->readOnly,
        ];
    }
}
