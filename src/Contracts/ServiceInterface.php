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
     * @param null|string $service
     * @return array|null
     */
    public static function getApiDocInfo($service);
}
