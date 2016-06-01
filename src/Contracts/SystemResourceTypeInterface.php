<?php
namespace DreamFactory\Core\Contracts;

/**
 * Interface SystemResourceTypeInterface
 *
 * Something that defines a System resource type
 *
 * @package DreamFactory\Core\Contracts
 */
interface SystemResourceTypeInterface
{
    /**
     * System resource type name - matching registered System resource types
     *
     * @return string
     */
    public function getName();

    /**
     * Displayable System resource type label
     *
     * @return string
     */
    public function getLabel();

    /**
     * System resource type description
     *
     * @return string
     */
    public function getDescription();

    /**
     * System resource type class handler
     *
     * @return string
     */
    public function getClassName();

    /**
     * Is this System resource type only to be created once?
     *
     * @return boolean
     */
    public function isSingleton();

    /**
     * Is this System resource type read only?
     *
     * @return boolean
     */
    public function isReadOnly();

    /**
     * Return the System resource type information as an array.
     *
     * @return array
     */
    public function toArray();
}
