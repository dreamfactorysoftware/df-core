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
    public function getApiDocInfo();
}
