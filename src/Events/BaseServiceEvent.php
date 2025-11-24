<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Models\Service;
use Illuminate\Queue\SerializesModels;

abstract class BaseServiceEvent
{
    use SerializesModels;

    public $service;

    /**
     * Create a new event instance.
     *
     * @param Service $service
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
    }
}
