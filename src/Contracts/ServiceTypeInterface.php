<?php
namespace DreamFactory\Core\Contracts;

/**
 * Interface ServiceTypeInterface
 *
 * Something that defines a service type
 *
 * @package DreamFactory\Core\Contracts
 */
interface ServiceTypeInterface
{
    /**
     * Service type name - matching registered service types
     *
     * @return string
     */
    public function getName();

    /**
     * Displayable service type label
     *
     * @return string
     */
    public function getLabel();

    /**
     * Service type description
     *
     * @return string
     */
    public function getDescription();

    /**
     * Displayable service type group label(s)
     *
     * @return string|array
     */
    public function getGroup();

    /**
     * Is this service type only to be created once?
     *
     * @return boolean
     */
    public function isSingleton();

    /**
     * Is a DreamFactory subscription required to use this service type
     *
     * @return boolean
     */
    public function isSubscriptionRequired();

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
     * The default API Document generator for this service type
     *
     * @param mixed $service
     *
     * @return array|null
     */
    public function getDefaultApiDoc($service);

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
     * Return the service type information as an array.
     *
     * @return array
     */
    public function toArray();
}
