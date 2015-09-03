<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Library\Utility\ArrayUtils;

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
        $serviceInfo = Service::getCachedByName($name);
        $serviceClass = ArrayUtils::get($serviceInfo, 'class_name');

        return new $serviceClass($serviceInfo);
    }

    public static function getServiceById($id)
    {
        $serviceInfo = Service::getCachedById($id);
        $serviceClass = ArrayUtils::get($serviceInfo, 'class_name');

        return new $serviceClass($serviceInfo);
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
     * @param string      $verb
     * @param string      $service
     * @param null  $resource
     * @param array $query
     * @param null  $payload
     * @param null  $format
     * @param array $header
     *
     * @return \DreamFactory\Core\Contracts\ServiceResponseInterface|mixed
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \Exception
     */
    public static function handleRequest($verb, $service, $resource = null, $query = [], $payload = null, $format = null, $header = [])
    {
        $_FILES = []; // reset so that internal calls can handle other files.
        $request = new ServiceRequest();
        $request->setMethod($verb);
        $request->setParameters($query);
        $request->setHeaders($header);
        if (!empty($payload)) {
            if (is_array($payload)) {
                $request->setContent($payload);
            } elseif (empty($format)) {
                throw new BadRequestException('Payload with undeclared format.');
            } else {
                $request->setContent($payload, $format);
            }
        }

        $response = self::getService($service)->handleRequest($request, $resource);

        if($response instanceof ServiceResponseInterface){
            return $response->getContent();
        } else {
            return $response;
        }
    }
}