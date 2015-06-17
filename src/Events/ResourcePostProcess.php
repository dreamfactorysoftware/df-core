<?php
namespace DreamFactory\Core\Events;

use Illuminate\Queue\SerializesModels;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;

class ResourcePostProcess extends Event
{
    use SerializesModels;

    public $service;

    public $resourcePath;

    public $request;

    public $response;

    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string                         $service
     * @param string                         $resource_path
     * @param ServiceRequestInterface        $request
     * @param array|ServiceResponseInterface $response
     * @param mixed                          $resource
     */
    public function __construct($service, $resource_path, $request, &$response, $resource = null)
    {
        $this->service = $service;
        $this->resourcePath = $resource_path;
        $this->request = $request;
        $this->response = $response;
        $this->resource = $resource;
    }
}
