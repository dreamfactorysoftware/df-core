<?php

namespace DreamFactory\Core\Jobs;

use Illuminate\Bus\Queueable;

abstract class Job
{
    /*
    |--------------------------------------------------------------------------
    | Queueable Jobs
    |--------------------------------------------------------------------------
    |
    | This job base class provides a central location to place any logic that
    | is shared across all of your jobs. The trait included with the class
    | provides access to the "queueOn" and "delay" queue helper methods.
    |
    */

    use Queueable;

    /**
     * Create a new job instance.
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->onConnection(array_get($config, 'connection'));
        $this->onQueue(array_get($config, 'queue'));
        $this->delay(array_get($config, 'delay'));
    }
}
