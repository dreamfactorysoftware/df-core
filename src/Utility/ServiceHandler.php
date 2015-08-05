<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Enums\DataFormats;

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
        $serviceInfo = CacheUtilities::getServiceInfo($name);
        $serviceClass = ArrayUtils::get($serviceInfo, 'class_name');

        return new $serviceClass($serviceInfo);
    }

    public static function getServiceById($id)
    {
        /** @type Service $service */
        $service = Service::find($id);

        if (empty($service)) {
            throw new NotFoundException("Could not find a service for ID $id");
        }

        if (!$service->is_active) {
            throw new ForbiddenException("Service $service->name is inactive.");
        }

        $serviceClass = $service->serviceType()->first()->class_name;
        $settings = $service->toArray();

        return new $serviceClass($settings);
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
     *
     * @return array
     */
    public static function listServices()
    {
        return ResourcesWrapper::wrapResources(Service::available());
    }

    /**
     * @param       $verb
     * @param       $service
     * @param null  $resource
     * @param array $query
     * @param null  $payload
     * @param array $header
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function handleRequest($verb, $service, $resource = null, $query = [], $payload = null, $header = [])
    {
        $_FILES = [];
        $request = new ServiceRequest();
        $request->setMethod($verb);
        $request->setParameters($query);
        $request->setHeaders($header);
        if (!empty($payload)) {
            if (is_array($payload)) {
                $request->setContent($payload);
            } else {
                $request->setContent($payload, DataFormats::JSON);
            }
        } else {
            $request->setContent(null);
        }

        $response = self::getService($service)->handleRequest($request, $resource);

        if($response instanceof ServiceResponseInterface){
            return $response->getContent();
        } else {
            return $response;
        }
    }
}