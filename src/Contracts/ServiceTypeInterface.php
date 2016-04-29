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
     * Service type - matching registered service types
     * 
     * @return string
     */
    public function getType();

    /**
     * Displayable service type label
     * 
     * @return string
     */
    public function getLabel();

    /**
     * Service type description
     * @return string
     */
    public function getDescription();

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
     * The configuration handler interface for this service type
     * 
     * @return ServiceConfigHandlerInterface | null
     */
    public function getConfigHandler();
}
