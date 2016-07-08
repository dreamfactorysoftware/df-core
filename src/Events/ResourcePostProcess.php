<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;

class ResourcePostProcess extends PostProcessApiEvent
{
    public $resourcePath;

    /**
     * Create a new event instance.
     *
     * @param string                   $service
     * @param string                   $resource_path
     * @param ServiceRequestInterface  $request
     * @param ServiceResponseInterface $response
     * @param mixed                    $resource
     */
    public function __construct($service, $resource_path, $request, &$response, $resource = null)
    {
        parent::__construct($service . '.' . $resource_path, $request, $response, $resource);
        $this->resourcePath = $resource_path;
    }
}
