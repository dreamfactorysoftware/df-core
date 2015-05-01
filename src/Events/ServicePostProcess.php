<?php
namespace DreamFactory\Rave\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;

class ServicePostProcess extends Event
{
    use SerializesModels;

    public $service;

    public $request;

    public $response;

    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string                         $service
     * @param ServiceRequestInterface        $request
     * @param array|ServiceResponseInterface $response
     * @param mixed                          $resource
     */
    public function __construct( $service, $request, &$response, $resource = null )
    {
        $this->service = $service;
        $this->request = $request;
        $this->response = $response;
        $this->resource = $resource;
    }
}
