<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;

class ResourcePreProcess extends PreProcessApiEvent
{
    public $resourcePath;

    /**
     * Create a new event instance.
     *
     * @param string                  $service
     * @param string                  $resource_path
     * @param ServiceRequestInterface $request
     * @param mixed                   $resource
     */
    public function __construct($service, $resource_path, &$request, $resource = null)
    {
        parent::__construct($service . '.' . $resource_path, $request, $resource);
        $this->resourcePath = $resource_path;
    }
}
