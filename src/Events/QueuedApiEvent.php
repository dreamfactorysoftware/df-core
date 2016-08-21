<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Jobs\ApiEventScriptJob;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Log;
use Crypt;

class QueuedApiEvent extends ApiEvent
{
    use DispatchesJobs;

    public $request;

    public $response;

    /**
     * Create a new event instance.
     *
     * @param string                   $path
     * @param ServiceRequestInterface  $request
     * @param ServiceResponseInterface $response
     * @param mixed                    $resource
     */
    public function __construct($path, $request, $response, $resource = null)
    {
        $name = strtolower($path . '.' . $request->getMethod()) . '.queued';
        parent::__construct($name);

        if ($response instanceof RedirectResponse || $response instanceof StreamedResponse) {
            $response = [];
        } else {
            $response = $response->toArray();
        }
        // these are serialized out to a foreign storage potentially, encrypt
        $this->request = Crypt::encrypt(json_encode($request->toArray()));
        $this->response = Crypt::encrypt(json_encode($response));
    }

    public function makeData()
    {
        $data = parent::makeData();
        $data['request'] = json_decode(Crypt::decrypt($this->request), true);
        $data['response'] = json_decode(Crypt::decrypt($this->response), true);

        return $data;
    }

    public function handle()
    {
        if (EventScript::whereName($this->name)->whereIsActive(true)->exists()) {
            $result = $this->dispatch(new ApiEventScriptJob($this->name, $this));
            Log::debug('API event queued: ' . $this->name . PHP_EOL . $result);
        }

        return true;
    }
}
