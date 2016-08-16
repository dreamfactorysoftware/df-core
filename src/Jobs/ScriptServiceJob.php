<?php

namespace DreamFactory\Core\Jobs;

use Crypt;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Scripting\Models\ScriptConfig;
use DreamFactory\Core\Utility\Session;
use Log;

class ScriptServiceJob extends ScriptJob
{
    public $service_id;

    public $resource;

    public $request;

    public $session;

    /**
     * Create a new job instance.
     * @param integer                 $id
     * @param ServiceRequestInterface $request
     * @param ServiceRequestInterface $resource
     */
    public function __construct($id, ServiceRequestInterface $request, $resource = null)
    {
        $this->service_id = $id;
        $this->resource = $resource;
        $this->request = Crypt::encrypt(json_encode($request->toArray()));
        $this->session = Crypt::encrypt(json_encode(\Session::all()));

        parent::__construct();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::notice('Queued Script handled for ' . $this->service_id);
        if (!empty($script = ScriptConfig::whereServiceId($this->service_id)->first())) {

            $script->content = Session::translateLookups($script->content, true);
            if (!is_array($script->config)) {
                $script->config = [];
            }

            $session = json_decode(Crypt::decrypt($this->session), true);
            \Session::replace($session);

            $data = [
                'resource' => $this->resource,
                'request'  => json_decode(Crypt::decrypt($this->request), true),
            ];

            $logOutput = (isset($data['request']['parameters']['log_output']) ? $data['request']['parameters']['log_output'] : true);
            if (null !== $this->handleScript('service.' . $script->name, $script->content, $script->engineType, $script->scriptConfig, $data, $logOutput)) {
                Log::notice('Queued Script success for ' . $script->service_id);
            }
        }
    }
}