<?php

namespace DreamFactory\Core\Jobs;

use DreamFactory\Core\Events\QueuedApiEvent;
use Crypt;
use Log;
use Session;

class ApiEventScriptJob extends ScriptJob
{
    public $script_id;

    public $event;

    public $session;

    /**
     * Create a new job instance.
     * @param integer        $id
     * @param QueuedApiEvent $event
     */
    public function __construct($id, QueuedApiEvent $event)
    {
        $this->script_id = $id;
        $this->event = $event;
        $this->session = Crypt::encrypt(json_encode(Session::all()));

        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::notice('Queued Script handled for ' . $this->script_id);
        if ($script = $this->event->getEventScript($this->script_id)) {
            $session = json_decode(Crypt::decrypt($this->session), true);
            Session::replace($session);

            $data = $this->event->makeData();
            if (null !== $this->handleScript($script->name, $script->content, $script->type, $script->config, $data)) {
                Log::notice('Queued Script success for '. $this->script_id);
            }
        }
    }
}