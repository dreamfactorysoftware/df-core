<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;

class PreProcessApiEvent extends InterProcessApiEvent
{
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
        parent::__construct($path, $request, $response, $resource);
        $this->name = $this->name . '.pre_process';
    }

    public function makeData()
    {
        return array_except(parent::makeData(), ['response']);
    }
}
