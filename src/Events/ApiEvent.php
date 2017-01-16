<?php
namespace DreamFactory\Core\Events;

abstract class ApiEvent extends Event
{
    public $resource;

    /**
     * Create a new event instance.
     *
     * @param string $path
     * @param mixed  $resource
     */
    public function __construct($path, $resource = null)
    {
        parent::__construct($path);
        $this->resource = $resource;
    }

    public function makeData()
    {
        return [
            'resource' => $this->resource
        ];
    }
}
