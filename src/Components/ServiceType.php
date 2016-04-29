<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;
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
     * @var string Designated type of this service
     */
    protected $type = '';
    /**
     * @var string Designated type of this service
     */
    protected $label = '';
    /**
     * @var string Designated type of this service
     */
    protected $description = '';
    /**
     * @var string Designated type of this service
     */
    protected $group = '';
    /**
     * @var string Designated type of this service
     */
    protected $singleton = false;
    /**
     * @var string Designated type of this service
     */
    protected $configHandler = null;
    
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
    public function getType()
    {
        return $this->type;
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
}
