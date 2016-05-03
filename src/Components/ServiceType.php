<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;
use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Contracts\ServiceTypeInterface;

/**
 * Interface ServiceType
 *
 * Something that defines a service type
 *
 * @package DreamFactory\Core\Contracts
 */
class ServiceType implements ServiceTypeInterface
{
    /**
     * @var string Designated type of a service
     */
    protected $name = '';
    /**
     * @var string Displayable label for this service type
     */
    protected $label = '';
    /**
     * @var string Description of this service type
     */
    protected $description = '';
    /**
     * @var string Designated group label for this service type
     */
    protected $group = '';
    /**
     * @var boolean True if this service type should only be created once per instance
     */
    protected $singleton = false;
    /**
     * @var string Designated configuration handler for this service type, typically ties to database storage
     */
    protected $configHandler = null;
    /**
     * @var callable Designated callback for creating a service of this type
     */
    protected $factory = null;

    /**
     * Create a new service type instance.
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
     * Service type - matching registered service types
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Displayable service type label
     *
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * Service type description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Displayable service type group label
     *
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Is this service type only to be created once?
     *
     * @return boolean
     */
    public function isSingleton()
    {
        return $this->singleton;
    }

    /**
     * The configuration handler interface for this service type
     *
     * @return ServiceConfigHandlerInterface | null
     */
    public function getConfigHandler()
    {
        return $this->configHandler;
    }

    /**
     * The configuration handler interface for this service type
     *
     * @param string $name
     * @param array $config
     *
     * @return ServiceInterface|null
     */
    public function make($name, array $config = [])
    {
        return call_user_func($this->factory, $config, $name);
    }

    /**
     * The configuration handler interface for this service type
     *
     * @return ServiceConfigHandlerInterface | null
     */
    public function toArray()
    {
        $configSchema = null;
        if ($this->configHandler) {
            $handler = $this->configHandler;
            /** @var ServiceConfigHandlerInterface $handler */
            $configSchema = $handler::getConfigSchema();
        }

        return [
            'name'          => $this->name,
            'label'         => $this->label,
            'description'   => $this->description,
            'group'         => $this->group,
            'singleton'     => $this->singleton,
            'config_schema' => $configSchema
        ];
    }
}
