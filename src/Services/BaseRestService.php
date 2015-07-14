<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Enums\VerbsMask;
use DreamFactory\Core\Events\ServicePostProcess;
use DreamFactory\Core\Events\ServicePreProcess;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class BaseRestService
 *
 * @package DreamFactory\Core\Services
 */
class BaseRestService extends RestHandler implements ServiceInterface
{
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
     * @param mixed $fields Use '*', comma-delimited string, or array of properties
     *
     * @return boolean|array
     */
    public function listResources($fields = null)
    {
        $resources = $this->getResources();
        if (!empty($resources)) {
            foreach ($resources as &$resource) {
                $resource['access'] = VerbsMask::maskToArray($this->getPermissions(ArrayUtils::get($resource, 'name')));
            }

            return static::cleanResources($resources, 'name', $fields);
        }

        return false;
    }

    /**
     * Handles GET action
     *
     * @return mixed
     */
    protected function handleGET()
    {
        $fields = $this->request->getParameter('fields');

        return $this->listResources($fields);
    }

    /**
     * @return ServiceResponseInterface
     */
    protected function respond()
    {
        if ($this->response instanceof ServiceResponseInterface) {
            return $this->response;
        } elseif ($this->response instanceof RedirectResponse){
            return $this->response;
        }

        return ResponseFactory::create($this->response, $this->nativeFormat);
    }

    /**
     * @param string $operation
     * @param string $resource
     *
     * @return bool
     */
    public function checkPermission($operation, $resource = null)
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;
        Session::checkServicePermission($operation, $this->name, $resource, $requestType);
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    public function getPermissions($resource = null)
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;

        return Session::getServicePermissions($this->name, $resource, $requestType);
    }

    public function getApiDocInfo()
    {
        $isWrapped = \Config::get('df.always_wrap_resources', false);
        $wrapper = ($isWrapped) ? \Config::get('df.resources_wrapper'): null;
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
                    'operations'  => [],
                    'description' => 'No operations currently defined for this service.',
                ],
            ],
            'models'       => [
                'ComponentList' => [
                    'id'         => 'ComponentList',
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
                'Resource'      => [
                    'id'         => 'Resource',
                    'properties' => [
                        'name' => [
                            'type'        => 'string',
                            'description' => 'Name of the resource.',
                        ],
                    ],
                ],
                'Resources'     => [
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
                'Success'       => [
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