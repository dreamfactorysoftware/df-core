<?php
namespace DreamFactory\Core\Events;

use Illuminate\Queue\SerializesModels;

abstract class Event
{
    use SerializesModels;

    public $name;

    abstract public function handle();

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
