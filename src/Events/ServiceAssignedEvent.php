<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Services\BaseRestService;

class ServiceAssignedEvent extends ServiceEvent
{
    /** @var array  */
    protected $map;

    /** @var ApiEvent */
    protected $event;

    /** @var BaseRestService */
    protected $service;

    public function __construct(BaseRestService $service, ApiEvent $parentEvent, array $map)
    {
        parent::__construct($parentEvent->name);
        $this->service = $service;
        $this->event = $parentEvent;
        $this->map = $map;
    }

    public function getParent()
    {
        return $this->event;
    }

    public function getData()
    {
        return $this->map;
    }

    public function getService()
    {
        return $this->service;
    }

    public function makeData()
    {
        return $this->event->makeData();
    }
}