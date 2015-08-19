<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Contracts\ResourceInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Events\ResourcePostProcess;
use DreamFactory\Core\Events\ResourcePreProcess;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ApiDocUtilities;
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

    /**
     * Runs pre process tasks/scripts
     */
    protected function preProcess()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire(
            new ResourcePreProcess(
                $this->getServiceName(), $this->getFullPathName('.'), $this->request, $this->resourcePath
            )
        );
    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {
        $event = new ResourcePostProcess(
            $this->getServiceName(), $this->getFullPathName('.'), $this->request, $this->response, $this->resourcePath
        );
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire($event);

        // todo doing something wrong that I have to copy this array back over
        $this->response = $event->response;
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
            $path = (!empty($path)) ? '/' . $resource : $resource;
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
            $path = (!empty($path)) ? '/' . $resource : $resource;
        }

        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;

        return Session::getServicePermissions($this->getServiceName(), $path, $requestType);
    }

    public function getApiDocModels()
    {
        $name = Inflector::camelize($this->name);
        $plural = Inflector::pluralize($name);
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            $plural . 'List'       => [
                'id'         => $plural . 'List',
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
            $name . 'Response'   => [
                'id'         => $name . 'Response',
                'properties' => [
                    $this->getResourceIdentifier()    => [
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
                        'description' => 'Array of resources available to this path.',
                        'items'       => [
                            '$ref' => $name . 'Response',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $name = Inflector::camelize($this->name);
        $plural = Inflector::pluralize($name);
        $words = str_replace('_', ' ', $this->name);
        $pluralWords = Inflector::pluralize($words);

        return [
            'apis'   => [
                [
                    'path'        => $path,
                    'description' => "Operations for $words administration.",
                    'operations'  => [
                        [
                            'method'           => 'GET',
                            'summary'          => 'get' .
                                $plural .
                                'List() - List all ' .
                                $pluralWords .
                                ' identifiers.',
                            'nickname'         => 'get' . $plural . 'List',
                            'notes'            => 'Return only a list of the resource identifiers.',
                            'type'             => $plural . 'List',
                            'event_name'       => [$eventPath . '.list'],
                            'parameters'       => [
                                ApiOptions::documentOption(ApiOptions::AS_LIST, true, true),
                                ApiOptions::documentOption(ApiOptions::ID_FIELD),
                                ApiOptions::documentOption(ApiOptions::ID_TYPE),
                                ApiOptions::documentOption(ApiOptions::REFRESH),
                            ],
                            'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        ],
                        [
                            'method'           => 'GET',
                            'summary'          => 'get' . $plural . '() - List all ' . $pluralWords . '.',
                            'nickname'         => 'get' . $plural,
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
            'models' => $this->getApiDocModels()
        ];
    }
}