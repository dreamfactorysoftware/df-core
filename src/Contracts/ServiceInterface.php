<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that behaves like a service and can handle service requests
 */
/**
 * Interface ServiceInterface
 *
 * @package DreamFactory\Core\Contracts
 */
interface ServiceInterface extends RequestHandlerInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getType();

    /**
     * @return boolean
     */
    public function isActive();

    /**
     * @return ServiceTypeInterface
     */
    public function getServiceTypeInfo();

    /**
     * @param null|string $resource
     *
     * @return array
     */
    public function getPermissions($resource = null);

    /**
     * @return array
     */
    public function getAccessList();

    /**
     * @return array|null
     */
    public function getEventMap();

    /**
     * @param bool $refresh
     * @return array|null
     */
    public function getApiDoc($refresh = false);
}
