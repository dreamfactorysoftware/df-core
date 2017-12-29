<?php
namespace DreamFactory\Core\Contracts;

use Illuminate\Contracts\Support\Arrayable;

/**
 * Interface SystemResourceInterface
 *
 * Something that behaves like a system resource and can handle service requests
 *
 * @package DreamFactory\Core\Contracts
 */
interface SystemResourceInterface extends NamedInstanceInterface, RequestHandlerInterface, Arrayable
{
    /**
     * @return SystemResourceTypeInterface
     */
    public static function getSystemResourceTypeInfo();

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
