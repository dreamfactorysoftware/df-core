<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Services\BaseRestService;

class ServiceEvent extends ApiEvent
{
    /** @var array  */
    protected $data;

    /** @var ApiEvent */
    protected $event;

    /** @var BaseRestService */
    protected $service;

    public function __construct(BaseRestService $service, ApiEvent $parentEvent, array $data)
    {
        $name = str_replace('.queued', null, $parentEvent->name);
        parent::__construct($name);
        $this->service = $service;
        $this->event = $parentEvent;
        $this->data = $data;
    }

    public function getParent()
    {
        return $this->event;
    }

    public function getData()
    {
        return $this->data;
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