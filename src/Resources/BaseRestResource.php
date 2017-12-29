<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Contracts\ResourceInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\Session;

/**
 * Class BaseRestResource
 *
 * @package DreamFactory\Core\Resources
 */
class BaseRestResource extends RestHandler implements ResourceInterface
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var RestHandler Object that requested this handler, null if this is the Service.
     */
    protected $parent = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return RequestHandlerInterface
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param RequestHandlerInterface $parent
     */
    public function setParent(RequestHandlerInterface $parent)
    {
        $this->parent = $parent;
    }

    public function getFullPathName($separator = '/')
    {
        if ($this->parent instanceof BaseRestResource) {
            return $this->parent->getFullPathName($separator) . $separator . $this->name;
        } else {
            // name of self
            return $this->name;
        }
    }

    public function getService()
    {
        if ($this->parent instanceof BaseRestService) {
            return $this->parent;
        } elseif ($this->parent instanceof BaseRestResource) {
            return $this->parent->getService();
        }

        return '';
    }

    public function getServiceName()
    {
        if ($this->parent instanceof BaseRestService) {
            return $this->parent->name;
        } elseif ($this->parent instanceof BaseRestResource) {
            return $this->parent->getServiceName();
        }

        return '';
    }

    public function getServiceId()
    {
        if ($this->parent instanceof BaseRestService) {
            return $this->parent->getServiceId();
        } elseif ($this->parent instanceof BaseRestResource) {
            return $this->parent->getServiceId();
        }

        return '';
    }

    protected function getEventName()
    {
        return $this->getServiceName() . '.' . $this->getFullPathName('.');
    }

    /**
     * @param string $operation
     * @param string $resource
     *
     * @return bool
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     */
    public function checkPermission($operation, $resource = null)
    {
        $path = $this->getFullPathName();
        if (!empty($resource)) {
            $path = (!empty($path)) ? $path . '/' . $resource : $resource;
        }

        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;

        Session::checkServicePermission($operation, $this->getServiceName(), $path, $requestType);

        return true;
    }

    /**
     * @param string $resource
     *
     * @return int
     */
    public function getPermissions($resource = null)
    {
        $path = $this->getFullPathName();
        if (!empty($resource)) {
            $path = (!empty($path)) ? $path . '/' . $resource : $resource;
        }

        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;

        return Session::getServicePermissions($this->getServiceName(), $path, $requestType);
    }

    public function getEventMap()
    {
        // By default, event map for resources are derived from the ApiDocInfo from the service level
        // Override this to add extra events not included in the ApiDocInfo.
        return [];
    }

    protected function getApiDocPaths()
    {
        $service = $this->getServiceName();
        $capitalized = camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower($this->name);
        $pluralClass = str_plural($class);
        if ($pluralClass === $class) {
            // method names can't be the same
            $pluralClass = $class . 'Entries';
        }

        $paths = [
            '/' . $resourceName => [
                'get' => [
                    'summary'     => 'Retrieve one or more ' . $pluralClass . '.',
                    'description' =>
                        'Use the \'ids\' or \'filter\' parameter to limit records that are returned. ' .
                        'By default, all records up to the maximum are returned. ' .
                        'Use the \'fields\' and \'related\' parameters to limit properties returned for each record. ' .
                        'By default, all fields and no relations are returned for each record. ' .
                        'Alternatively, to retrieve by record, a large list of ids, or a complicated filter, ' .
                        'use the POST request with X-HTTP-METHOD = GET header and post records or ids.',
                    'operationId' => 'get' . $capitalized . $pluralClass,
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::RELATED),
                        ApiOptions::documentOption(ApiOptions::IDS),
                        ApiOptions::documentOption(ApiOptions::FILTER),
                        ApiOptions::documentOption(ApiOptions::LIMIT),
                        ApiOptions::documentOption(ApiOptions::ORDER),
                        ApiOptions::documentOption(ApiOptions::GROUP),
                        ApiOptions::documentOption(ApiOptions::OFFSET),
                        ApiOptions::documentOption(ApiOptions::COUNT_ONLY),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_COUNT),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_SCHEMA),
                        ApiOptions::documentOption(ApiOptions::FILE),
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $pluralClass . 'Response']
                    ],
                ],
            ],
        ];

        return $paths;
    }
}