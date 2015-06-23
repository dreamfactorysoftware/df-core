<?php
namespace DreamFactory\Core\Events;

use Illuminate\Queue\SerializesModels;
use DreamFactory\Core\Contracts\ServiceRequestInterface;

class ServicePreProcess extends Event
{
    use SerializesModels;

    public $service;

    public $request;

    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string                  $service
     * @param ServiceRequestInterface $request
     * @param mixed                   $resource
     */
    public function __construct($service, &$request, $resource = null)
    {
        $this->service = $service;
        $this->request = $request;
        $this->resource = $resource;
    }
}
