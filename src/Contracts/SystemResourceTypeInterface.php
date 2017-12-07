<?php
namespace DreamFactory\Core\Contracts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Interface SystemResourceTypeInterface
 *
 * Something that defines a System resource type
 *
 * @package DreamFactory\Core\Contracts
 */
interface SystemResourceTypeInterface extends NamedInstanceInterface, Arrayable
{
    /**
     * System resource type class handler
     *
     * @return string
     */
    public function getClassName();

    /**
     * DreamFactory subscription required to use this system resource, null if none required
     *
     * @return null|string
     */
    public function subscriptionRequired();

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
}
