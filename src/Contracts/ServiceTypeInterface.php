<?php

namespace DreamFactory\Core\Contracts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Interface ServiceTypeInterface
 *
 * Something that defines a service type
 *
 * @package DreamFactory\Core\Contracts
 */
interface ServiceTypeInterface extends NamedInstanceInterface, Arrayable
{
    /**
     * Displayable service type group label
     *
     * @return string
     */
    public function getGroup();

    /**
     * Is this service type only to be created once?
     *
     * @return boolean
     */
    public function isSingleton();

    /**
     * DreamFactory subscription required to use this service type, null if none required
     *
     * @return null|string
     */
    public function subscriptionRequired();

    /**
     * Is the service definition (OpenAPI document) editable. False if auto-generated.
     *
     * @return boolean
     */
    public function isServiceDefinitionEditable();

    /**
     * Are there any dependencies (i.e. drivers, other service types, etc.) that are not met to use this service type.
     *
     * @return null | string
     */
    public function getDependenciesRequired();

    /**
     * The configuration handler interface for this service type
     *
     * @return ServiceConfigHandlerInterface | null
     */
    public function getConfigHandler();

    /**
     * The factory interface for this service type
     *
     * @param string $name
     * @param array  $config
     *
     * @return \DreamFactory\Core\Contracts\ServiceInterface|null
     */
    public function make($name, array $config = []);

    /**
     * Get any allowed access exceptions for this service, i.e. allow bypass of RBAC
     *
     * @return array
     */
    public function getAccessExceptions();

    /**
     * Is the path a role access exception for this service type
     *
     * @param string|int  $action
     * @param string|null $path
     *
     * @return boolean
     */
    public function isAccessException($action, $path = null);
}
