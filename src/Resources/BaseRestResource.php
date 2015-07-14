<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\RequestHandlerInterface;
use DreamFactory\Core\Contracts\ResourceInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Events\ResourcePostProcess;
use DreamFactory\Core\Events\ResourcePreProcess;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\Session;

/**
 * Class BaseRestResource
 *
 * @package DreamFactory\Core\Resources
 */
class BaseRestResource extends RestHandler implements ResourceInterface
{
    /**
     * @var RestHandler Object that requested this handler, null if this is the Service.
     */
    protected $parent = null;

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

    /**
     * @param mixed $fields Use '*', comma-delimited string, or array of properties
     *
     * @return boolean|array
     */
    public function listResources($fields = null)
    {
        $resources = $this->getResources();
        if (!empty($resources)) {
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

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();

        /**
         * Some basic apis and models used in DSP REST interfaces
         */

        return [
            'apis'   => [
                [
                    'path'        => $path,
                    'operations'  => [],
                    'description' => 'No operations currently defined for this resource.',
                ],
            ],
            'models' => []
        ];
    }
}