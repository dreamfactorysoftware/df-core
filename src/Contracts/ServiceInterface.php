<?php
namespace DreamFactory\Core\Contracts;

/**
 * Interface ServiceInterface
 *
 * Something that behaves like a service and can handle service requests
 *
 * @package DreamFactory\Core\Contracts
 */
interface ServiceInterface extends NamedInstanceInterface, RequestHandlerInterface
{
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
