<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseRestService;

/**
 * Class ServiceHandler
 *
 * @package DreamFactory\Core\Utility
 */
class ServiceHandler
{
    /**
     * @param $name
     *
     * @return BaseRestService
     * @throws ForbiddenException
     * @throws NotFoundException
     */
    public static function getService($name)
    {
        $name = strtolower(trim($name));

        $service = Service::whereName($name)->get()->first();

        if (empty($service)) {
            throw new NotFoundException("Could not find a service for $name");
        }

        return static::getServiceInternal($service);
    }

    public static function getServiceById($id)
    {
        $service = Service::find($id);

        if (empty($service)) {
            throw new NotFoundException("Could not find a service for ID $id");
        }

        return static::getServiceInternal($service);
    }

    protected static function getServiceInternal($service)
    {
        if ($service instanceof Service) {
            if ($service->is_active) {
                $serviceClass = $service->serviceType()->first()->class_name;
                $settings = $service->toArray();

                return new $serviceClass($settings);
            }

            throw new ForbiddenException("Service $service->name is inactive.");
        }

        throw new NotFoundException("Could not find a service for $service->name.");
    }

    /**
     * @param null|string $version
     * @param             $service
     * @param null        $resource
     *
     * @return mixed
     * @throws NotFoundException
     */
    public static function processRequest($version, $service, $resource = null)
    {
        $request = new ServiceRequest();
        $request->setApiVersion($version);

        return self::getService($service)->handleRequest($request, $resource);
    }

    /**
     * @param bool $include_properties
     *
     * @return array
     */
    public static function listServices($include_properties = false)
    {
        $services = Service::available($include_properties);

        return ['service' => $services];
    }
}