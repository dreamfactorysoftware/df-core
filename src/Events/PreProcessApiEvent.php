<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;

class PreProcessApiEvent extends InterProcessApiEvent
{
    public $request;

    public $response;

    /**
     * Create a new event instance.
     *
     * @param string                   $path
     * @param ServiceRequestInterface  $request
     * @param ServiceResponseInterface $response
     * @param mixed                    $resource
     */
    public function __construct($path, &$request, &$response, $resource = null)
    {
        $this->request = $request;
        $this->response = $response;
        $name = strtolower($path . '.' . str_replace('/', '.', $resource) . '.' . $request->getMethod()) . '.pre_process';
        parent::__construct($name, $resource);
    }

    public function makeData()
    {
        return [
            'request'  => $this->request->toArray(),
            'resource' => $this->resource,
        ];
    }
}
