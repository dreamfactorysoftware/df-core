<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session;
use ServiceManager as ServiceMgr;

/**
 * Class BaseRestService
 *
 * @package DreamFactory\Core\Services
 */
class BaseRestService extends RestHandler implements ServiceInterface
{
    const RESOURCE_IDENTIFIER = 'name';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var integer|null Database Id of the services entry
     */
    protected $id = null;
    /**
     * @var string Designated type of this service
     */
    protected $type;
    /**
     * @var boolean Is this service activated for use?
     */
    protected $isActive = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return int
     */
    public function getServiceId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return ServiceTypeInterface
     */
    public function getServiceTypeInfo()
    {
        if (null !== $typeInfo = ServiceMgr::getServiceType($this->type)) {
            return $typeInfo;
        }

        return null;
    }

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->isActive;
    }

    public function handleRequest(ServiceRequestInterface $request, $resource = null)
    {
        if (!$this->isActive) {
            throw new ForbiddenException("Service {$this->name} is deactivated.");
        }

        return parent::handleRequest($request, $resource);
    }

    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return static::RESOURCE_IDENTIFIER;
    }

    /**
     * {@inheritdoc}
     */
    public function checkPermission($operation, $resource = null)
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;
        Session::checkServicePermission($operation, $this->name, $resource, $requestType);
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions($resource = null)
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;

        return Session::getServicePermissions($this->name, $resource, $requestType);
    }

    public function getAccessList()
    {
        if (!empty($this->getPermissions())) {
            return ['', '*'];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function handleGET()
    {
        if ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST)) {
            return ResourcesWrapper::wrapResources($this->getAccessList());
        }

        return parent::handleGET();
    }

    public static function getApiDocInfo($service)
    {
        $name = strtolower($service->name);
        $capitalized = camelize($service->name);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'paths'       => [
                '/' . $name => [
                    'get' => [
                        'tags'              => [$name],
                        'summary'           => 'get' . $capitalized . 'Resources() - Get resources for this service.',
                        'operationId'       => 'get' . $capitalized . 'Resources',
                        'description'       => 'Return an array of the resources available.',
                        'parameters'        => [
                            ApiOptions::documentOption(ApiOptions::AS_LIST),
                            ApiOptions::documentOption(ApiOptions::AS_ACCESS_LIST),
                            ApiOptions::documentOption(ApiOptions::INCLUDE_ACCESS),
                            ApiOptions::documentOption(ApiOptions::FIELDS),
                            ApiOptions::documentOption(ApiOptions::ID_FIELD),
                            ApiOptions::documentOption(ApiOptions::ID_TYPE),
                            ApiOptions::documentOption(ApiOptions::REFRESH),
                        ],
                        'responses'         => [
                            '200'     => [
                                'description' => 'Success',
                                'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Response']
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
                            'description' => 'Array of resources available to this service.',
                            'items'       => [
                                '$ref' => '#/definitions/' . $class . 'Response',
                            ],
                        ],
                    ],
                ],
            ]
        ];
    }
}