<?php

namespace DreamFactory\Core\Services;

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
     * @var string If this service type requires dependencies that are not installed, list them here for client.
     */
    protected $dependenciesRequired = null;
    /**
     * @var boolean True if this service type requires a paid subscription, that has not been designated
     */
    protected $subscriptionRequired = false;
    /**
     * @var boolean True if this service allows editing the service definition, i.e. swagger def.
     */
    protected $serviceDefinitionEditable = false;
    /**
     * @var ServiceConfigHandlerInterface Designated configuration handler for this service type, typically ties to
     *      database storage
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

    public function getName()
    {
        return $this->name;
    }

    public function getLabel()
    {
        return $this->label;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getGroup()
    {
        return $this->group;
    }

    public function isSingleton()
    {
        return $this->singleton;
    }

    public function getConfigHandler()
    {
        return $this->configHandler;
    }

    public function isSubscriptionRequired()
    {
        return $this->subscriptionRequired;
    }

    public function isServiceDefinitionEditable()
    {
        return $this->serviceDefinitionEditable;
    }

    public function getDependenciesRequired()
    {
        return $this->dependenciesRequired;
    }

    public function make($name, array $config = [])
    {
        return call_user_func($this->factory, $config, $name);
    }

    public function toArray()
    {
        $configSchema = null;
        if ($this->configHandler) {
            $handler = $this->configHandler;
            $configSchema = $handler::getConfigSchema();
        }

        return [
            'name'                        => $this->name,
            'label'                       => $this->label,
            'description'                 => $this->description,
            'group'                       => $this->group,
            'singleton'                   => $this->singleton,
            'dependencies_required'       => $this->dependenciesRequired,
            'subscription_required'       => $this->subscriptionRequired,
            'service_definition_editable' => $this->serviceDefinitionEditable,
            'config_schema'               => $configSchema
        ];
    }
}
