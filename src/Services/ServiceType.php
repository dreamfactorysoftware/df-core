<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\ServiceConfigHandlerInterface;
use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Enums\DbSimpleTypes;
use DreamFactory\Core\Enums\VerbsMask;

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
     * @var string If this service type requires a paid subscription, which one
     */
    protected $subscriptionRequired = null;
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
     * @var callable Designated role access exceptions for handling service of this type
     */
    protected $accessExceptions = null;

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

    public function subscriptionRequired()
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

    public function getAccessExceptions()
    {
        return $this->accessExceptions;
    }

    public function isAccessException($action, $path = null)
    {
        if (is_string($action)) {
            $action = VerbsMask::toNumeric($action);
        }
        if (isset($this->accessExceptions)) {
            foreach ($this->accessExceptions as $exception) {
                if (($action & array_get($exception, 'verb_mask')) &&
                    (('*' === array_get($exception, 'resource')) ||
                        ($path === array_get($exception, 'resource')))
                ) {
                    return true;
                }
            }
        }

        return false;
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

    public static function getSchema()
    {
        return [
            'name'        => 'service_type',
            'description' => 'The type definition for a service.',
            'type'        => DbSimpleTypes::TYPE_OBJECT,
            'properties'  => [
                'name'                        => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Identifier for the service type.',
                ],
                'label'                       => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Displayable label for the service type.',
                ],
                'description'                 => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Description of the service type.',
                ],
                'group'                       => [
                    'type'        => DbSimpleTypes::TYPE_STRING,
                    'description' => 'Group this type belongs to.',
                ],
                'singleton'                   => [
                    'type'        => DbSimpleTypes::TYPE_BOOLEAN,
                    'description' => 'Can there only be one service of this type in the system?',
                ],
                'dependencies_required'       => [
                    'type'        => DbSimpleTypes::TYPE_BOOLEAN,
                    'description' => 'Does this service type have any dependencies?',
                ],
                'subscription_required'       => [
                    'type'        => DbSimpleTypes::TYPE_BOOLEAN,
                    'description' => 'Does this service type require a paid subscription to use?',
                ],
                'service_definition_editable' => [
                    'type'        => DbSimpleTypes::TYPE_BOOLEAN,
                    'description' => 'Is the configuration of this service editable?',
                ],
                'config_schema'               => [
                    'type'        => DbSimpleTypes::TYPE_ARRAY,
                    'description' => 'Configuration options for this service type.',
                    'items'       => [
                        'type'       => DbSimpleTypes::TYPE_OBJECT,
                        'properties' => [
                            'alias'       => [
                                'type'        => DbSimpleTypes::TYPE_STRING,
                                'description' => 'Optional alias of the option.',
                            ],
                            'name'        => [
                                'type'        => DbSimpleTypes::TYPE_STRING,
                                'description' => 'Name of the option.',
                            ],
                            'label'       => [
                                'type'        => DbSimpleTypes::TYPE_STRING,
                                'description' => 'Displayed name of the option.',
                            ],
                            'description' => [
                                'type'        => DbSimpleTypes::TYPE_STRING,
                                'description' => 'Description of the option.',
                            ],
                            'type'        => [
                                'type'        => DbSimpleTypes::TYPE_STRING,
                                'description' => 'Data type of the option for storage.',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
