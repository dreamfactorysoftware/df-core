<?php

namespace DreamFactory\Core\Jobs;

use DreamFactory\Core\Components\ScriptHandler;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ScriptJob extends Job implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels, ScriptHandler;

    /**
     * Create a new job instance.
     *
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //
    }
}
