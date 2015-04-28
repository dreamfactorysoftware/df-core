<?php
namespace DreamFactory\Rave\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;

class ServicePreProcess extends Event
{
    use SerializesModels;

    public $service;

    public $verb;

    public $request;

    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string                  $service
     * @param string                  $verb
     * @param ServiceRequestInterface $request
     * @param mixed                   $resource
     */
    public function __construct( $service, $verb, $request, $resource = null )
    {
        $this->service = $service;
        $this->verb = $verb;
        $this->request = $request;
        $this->resource = $resource;
    }
}
