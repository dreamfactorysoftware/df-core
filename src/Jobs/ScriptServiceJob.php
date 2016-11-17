<?php

namespace DreamFactory\Core\Jobs;

use Crypt;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Models\Service;
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
     * @param array                   $config
     */
    public function __construct($id, ServiceRequestInterface $request, $resource = null, $config = [])
    {
        $this->service_id = $id;
        $this->resource = $resource;
        $this->request = Crypt::encrypt(json_encode($request->toArray()));
        $this->session = Crypt::encrypt(json_encode(\Session::all()));

        parent::__construct($config);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::notice('Queued Script handled for ' . $this->service_id);
        if (!empty($service = Service::whereId($this->service_id)->first())) {

            $service->protectedView = false;
            $script = $service->getConfigAttribute();
            $script['content'] = Session::translateLookups(array_get($script, 'content'), true);
            if (!isset($script['config']) || !is_array($script['config'])) {
                $script['config'] = [];
            }

            $session = json_decode(Crypt::decrypt($this->session), true);
            \Session::replace($session);

            $data = [
                'resource' => $this->resource,
                'request'  => json_decode(Crypt::decrypt($this->request), true),
            ];

            $logOutput = (isset($data['request']['parameters']['log_output']) ? $data['request']['parameters']['log_output'] : true);
            if (null !== $this->handleScript('service.' . $service->name, $script['content'], $service->type,
                    $script['config'], $data, $logOutput)
            ) {
                Log::notice('Queued Script success for ' . $service->name);
            }
        }
    }
}