<?php
namespace DreamFactory\Rave\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;

class ResourcePreProcess extends Event
{
    use SerializesModels;

    public $service;

    public $resourcePath;

    public $request;

    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string                  $service
     * @param string                  $resource_path
     * @param ServiceRequestInterface $request
     * @param mixed                   $resource
     */
    public function __construct( $service, $resource_path, &$request, $resource = null )
    {
        $this->service = $service;
        $this->resourcePath = $resource_path;
        $this->request = $request;
        $this->resource = $resource;
    }
}
