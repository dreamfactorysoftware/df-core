<?php
namespace DreamFactory\Core\Events;

class ServiceEvent extends Event
{
    public $resource;
    public $data;

    /**
     * Create a new event instance.
     *
     * @param string $name
     * @param mixed  $resource
     * @param array  $data
     */
    public function __construct($name, $resource = null, $data = null)
    {
        parent::__construct($name);
        $this->resource = $resource;
        $this->data = $data;
    }

    public function makeData()
    {
        return array_merge(['resource' => $this->resource], (array)$this->data);
    }
}
