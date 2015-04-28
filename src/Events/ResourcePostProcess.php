<?php
namespace DreamFactory\Rave\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;

class ResourcePostProcess extends Event
{
    use SerializesModels;

    public $service;

    public $resourcePath;

    public $verb;

    public $request;

    public $response;

    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string                   $service
     * @param string                   $resource_path
     * @param string                   $verb
     * @param ServiceRequestInterface  $request
     * @param ServiceResponseInterface $response
     * @param mixed                    $resource
     */
    public function __construct( $service, $resource_path, $verb, $request, $response, $resource = null )
    {
        $this->service = $service;
        $this->resourcePath = $resource_path;
        $this->verb = $verb;
        $this->request = $request;
        $this->response = $response;
        $this->resource = $resource;
    }
}
