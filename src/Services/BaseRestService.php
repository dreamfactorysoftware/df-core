<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Events\ServicePostProcess;
use DreamFactory\Core\Events\ServicePreProcess;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Inflector;
use Symfony\Component\HttpFoundation\RedirectResponse;
use ServiceManager as ServiceMgr;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function getServiceTypeInfo()
    {
        if (null !== $typeInfo = ServiceMgr::getServiceType($this->type)) {
            return $typeInfo;
        }

        return null;
    }

    public function handleRequest(ServiceRequestInterface $request, $resource = null)
    {
        if (!$this->isActive) {
            throw new ForbiddenException("Service {$this->name} is deactivated.");
        }

        return parent::handleRequest($request, $resource);
    }

    /**
     * Runs pre process tasks/scripts
     */
    protected function preProcess()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire(new ServicePreProcess($this->name, $this->request, $this->resourcePath));
    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {
        $event = new ServicePostProcess($this->name, $this->request, $this->response, $this->resourcePath);
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire($event);
    }

    /**
     * @return ServiceResponseInterface
     */
    protected function respond()
    {
        if ($this->response instanceof ServiceResponseInterface ||
            $this->response instanceof RedirectResponse ||
            $this->response instanceof StreamedResponse
        ) {
            return $this->response;
        }

        return ResponseFactory::create($this->response);
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

    public function getApiDocInfo()
    {
        $name = strtolower($this->name);
        $capitalized = Inflector::camelize($this->name);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = Inflector::pluralize($class);
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'paths'       => [
                '/' . $name => [
                    'get' => [
                        'tags'              => [$name],
                        'summary'           => 'get' . $capitalized . 'Resources() - Get resources for this service.',
                        'operationId'       => 'get' . $capitalized . 'Resources',
                        'description'       => 'Return an array of the resources available.',
                        'x-publishedEvents' => [$name . '.list'],
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