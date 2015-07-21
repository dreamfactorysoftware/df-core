<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Enums\ApiOptions;
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
use DreamFactory\Library\Utility\Enums\Verbs;
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
            return ['','*'];
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
        $wrapper = ResourcesWrapper::getWrapper();

        /**
         * Some basic apis and models used in DSP REST interfaces
         */

        return [
            'resourcePath' => '/' . $this->name,
            'produces'     => ['application/json', 'application/xml'],
            'consumes'     => ['application/json', 'application/xml'],
            'apis'         => [
                [
                    'path'        => '/' . $this->name,
                    'description' => "Operations available for the {$this->label} service.",
                    'operations'  => [
                        [
                            'method'           => 'GET',
                            'summary'          => 'getResourceList() - List all resource names.',
                            'nickname'         => 'getResourceList',
                            'notes'            => 'Return only a list of the resource identifiers.',
                            'type'             => 'ResourceList',
                            'event_name'       => [$this->name . '.list'],
                            'parameters'       => [
                                ApiOptions::documentOption(ApiOptions::AS_LIST),
                                ApiOptions::documentOption(ApiOptions::AS_ACCESS_LIST),
                                ApiOptions::documentOption(ApiOptions::FIELDS),
                                ApiOptions::documentOption(ApiOptions::REFRESH),
                            ],
                            'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        ],
                        [
                            'method'           => 'GET',
                            'summary'          => 'getResources() - List all resources.',
                            'nickname'         => 'getResources',
                            'notes'            => 'List the resources available on this service. ',
                            'type'             => 'Resources',
                            'event_name'       => [$this->name . '.list'],
                            'parameters'       => [
                                ApiOptions::documentOption(ApiOptions::FIELDS),
                                ApiOptions::documentOption(ApiOptions::REFRESH),
                            ],
                            'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        ],
                    ],
                ],
            ],
            'models'       => [
                'ResourceList' => [
                    'id'         => 'ResourceList',
                    'properties' => [
                        $wrapper => [
                            'type'        => 'Array',
                            'description' => 'Array of accessible components available by this service.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
                'Resource'     => [
                    'id'         => 'Resource',
                    'properties' => [
                        '_id_'    => [
                            'type'        => 'string',
                            'description' => 'Identifier of the resource.',
                        ],
                        '_other_' => [
                            'type'        => 'string',
                            'description' => 'Other property of the resource.',
                        ],
                    ],
                ],
                'Resources'    => [
                    'id'         => 'Resources',
                    'properties' => [
                        $wrapper => [
                            'type'        => 'Array',
                            'description' => 'Array of resources available by this service.',
                            'items'       => [
                                '$ref' => 'Resource',
                            ],
                        ],
                    ],
                ],
                'Success'      => [
                    'id'         => 'Success',
                    'properties' => [
                        'success' => [
                            'type'        => 'boolean',
                            'description' => 'True when API call was successful, false or error otherwise.',
                        ],
                    ],
                ],
            ],
        ];
    }
}