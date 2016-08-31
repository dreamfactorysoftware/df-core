<?php
namespace DreamFactory\Core\Services;

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
     * @var string|array Designated group label for this service type, array if multiple
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
     * @var ServiceConfigHandlerInterface Designated configuration handler for this service type, typically ties to database storage
     */
    protected $configHandler = null;
    /**
     * @var callable Designated callback for retrieving the default API Doc for this service type
     */
    protected $defaultApiDoc = null;
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
     * Displayable service type group label(s)
     *
     * @return string|array
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
     * Is a DreamFactory subscription required to use this service type
     *
     * @return boolean
     */
    public function isSubscriptionRequired()
    {
        return $this->subscriptionRequired;
    }

    /**
     * Are there any dependencies (i.e. drivers, other service types, etc.) that are not met to use this service type.
     *
     * @return null | string
     */
    public function getDependenciesRequired()
    {
        return $this->dependenciesRequired;
    }

    /**
     * The default API Document generator for this service type
     *
     * @param mixed $service
     *
     * @return array|null
     */
    public function getDefaultApiDoc($service)
    {
        return call_user_func($this->defaultApiDoc, $service);
    }

    /**
     * The configuration handler interface for this service type
     *
     * @param string $name
     * @param array  $config
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
     * @return array | null
     */
    public function toArray()
    {
        $configSchema = null;
        if ($this->configHandler) {
            $handler = $this->configHandler;
            $configSchema = $handler::getConfigSchema();
        }

        return [
            'name'                  => $this->name,
            'label'                 => $this->label,
            'description'           => $this->description,
            'group'                 => $this->group,
            'singleton'             => $this->singleton,
            'dependencies_required' => $this->dependenciesRequired,
            'subscription_required' => $this->subscriptionRequired,
            'config_schema'         => $configSchema
        ];
    }
}
