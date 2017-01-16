<?php
namespace DreamFactory\Core\Events;

abstract class Event
{
    public $name;

    /**
     * Create a new event instance.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }
}
