<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Contracts\ResourceInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Inflector;

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
     */
    public function checkPermission($operation, $resource = null)
    {
        $path = $this->getFullPathName();
        if (!empty($resource)) {
            $path = (!empty($path)) ? $path . '/' . $resource : $resource;
        }

        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;

        Session::checkServicePermission($operation, $this->getServiceName(), $path, $requestType);
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

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = Inflector::camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $pluralClass = Inflector::pluralize($class);
        $path = '/' . $serviceName . '/' . $resourceName;
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'paths'       => [
                $path => [
                    'get' =>
                        [
                            'tags'              => [$serviceName],
                            'summary'           => 'get' .
                                $capitalized .
                                $pluralClass .
                                '() - List all ' .
                                $pluralClass,
                            'operationId'       => 'get' . $capitalized . $pluralClass,
                            'description'       => 'Return a list of the resource identifiers.',
                            'parameters'        => [
                                ApiOptions::documentOption(ApiOptions::AS_LIST),
                                ApiOptions::documentOption(ApiOptions::ID_FIELD),
                                ApiOptions::documentOption(ApiOptions::ID_TYPE),
                                ApiOptions::documentOption(ApiOptions::REFRESH),
                            ],
                            'responses'         => [
                                '200'     => [
                                    'description' => 'Success',
                                    'schema'      => [
                                        '$ref' => '#/definitions/' .
                                            $pluralClass .
                                            'Response'
                                    ]
                                ],
                                'default' => [
                                    'description' => 'Error',
                                    'schema'      => ['$ref' => '#/definitions/Error']
                                ]
                            ],
                        ],
                ],
            ],
            'definitions' => [
                $pluralClass . 'List'     => [
                    'type'       => 'object',
                    'properties' => [
                        $wrapper => [
                            'type'        => 'array',
                            'description' => 'Array of accessible resources available to this path.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
                $class . 'Response'       => [
                    'type'       => 'object',
                    'properties' => [
                        static::getResourceIdentifier() => [
                            'type'        => 'string',
                            'description' => 'Identifier of the resource.',
                        ],
                    ],
                ],
                $pluralClass . 'Response' => [
                    'type'       => 'object',
                    'properties' => [
                        $wrapper => [
                            'type'        => 'array',
                            'description' => 'Array of resources available to this path.',
                            'items'       => [
                                '$ref' => '#/definitions/' . $class . 'Response',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}