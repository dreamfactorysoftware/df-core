<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Utility\ApiDocUtilities;
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

        // todo doing something wrong that I have to copy this array back over
        $this->response = $event->response;
    }

    /**
     * @return ServiceResponseInterface
     */
    protected function respond()
    {
        if ($this->response instanceof ServiceResponseInterface) {
            return $this->response;
        } elseif ($this->response instanceof RedirectResponse) {
            return $this->response;
        }

        return ResponseFactory::create($this->response, $this->nativeFormat);
    }

    /**
     * {@inheritdoc}
     */
    protected function getResourceIdentifier()
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

    protected function getAccessList()
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

    public function getApiDocModels()
    {
        $name = Inflector::camelize($this->name);
        $plural = Inflector::pluralize($name);
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'ResourceList'       => [
                'id'         => 'ResourceList',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of accessible resources available to this service.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            $name . 'Response'   => [
                'id'         => $name . 'Response',
                'properties' => [
                    $this->getResourceIdentifier() => [
                        'type'        => 'string',
                        'description' => 'Identifier of the resource.',
                    ],
                ],
            ],
            $plural . 'Response' => [
                'id'         => $plural . 'Response',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of resources available to this service.',
                        'items'       => [
                            '$ref' => $name . 'Response',
                        ],
                    ],
                ],
            ],
            'Success'            => [
                'id'         => 'Success',
                'properties' => [
                    'success' => [
                        'type'        => 'boolean',
                        'description' => 'True when API call was successful, false or error otherwise.',
                    ],
                ],
            ],
        ];
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->name;
        $eventPath = $this->name;
        $name = Inflector::camelize($this->name);
        $plural = Inflector::pluralize($name);

        return [
            'resourcePath' => $path,
            'produces'     => ['application/json', 'application/xml'],
            'consumes'     => ['application/json', 'application/xml'],
            'apis'         => [
                [
                    'path'        => $path,
                    'description' => "Operations available for the {$this->label} service.",
                    'operations'  => [
                        [
                            'method'           => 'GET',
                            'summary'          => 'getResourceList() - List all resource names.',
                            'nickname'         => 'getResourceList',
                            'notes'            => 'Return only a list of the resource identifiers.',
                            'type'             => 'ResourceList',
                            'event_name'       => [$eventPath . '.list'],
                            'parameters'       => [
                                ApiOptions::documentOption(ApiOptions::AS_LIST, true, true),
                                ApiOptions::documentOption(ApiOptions::AS_ACCESS_LIST),
                                ApiOptions::documentOption(ApiOptions::ID_FIELD),
                                ApiOptions::documentOption(ApiOptions::ID_TYPE),
                                ApiOptions::documentOption(ApiOptions::REFRESH),
                            ],
                            'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        ],
                        [
                            'method'           => 'GET',
                            'summary'          => 'getResources() - List all resources.',
                            'nickname'         => 'getResources',
                            'notes'            => 'List the resources available on this service. ',
                            'type'             => $plural . 'Response',
                            'event_name'       => [$eventPath . '.list'],
                            'parameters'       => [
                                ApiOptions::documentOption(ApiOptions::FIELDS),
                                ApiOptions::documentOption(ApiOptions::REFRESH),
                            ],
                            'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        ],
                    ],
                ],
            ],
            'models'       => $this->getApiDocModels()
        ];
    }
}