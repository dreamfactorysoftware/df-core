<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Utility\ResponseFactory;

class PreProcessApiEvent extends InterProcessApiEvent
{
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
    public function __construct($path, &$request, &$response, $resource = null)
    {
        $this->request = $request;
        $this->response = $response;
        $name = strtolower($path . '.' . $request->getMethod()) . '.pre_process';
        parent::__construct($name, $resource);
    }

    public function makeData()
    {
        return [
            'request'  => $this->request->toArray(),
            'resource' => $this->resource,
        ];
    }

    /**
     * @param EventScript $script
     * @param             $result
     *
     * @return bool
     */
    protected function handleEventScriptResult($script, $result)
    {
        if ($script->allow_event_modification) {
            // request only
            $this->request->mergeFromArray((array)array_get($result, 'request'));

            // new feature to allow pre-process to circumvent process by returning response
            if (!empty($response = array_get($result, 'response'))) {
                if (is_array($response) && array_key_exists('content', $response)) {
                    $content = array_get($response, 'content');
                    $contentType = array_get($response, 'content_type');
                    $status = array_get($response, 'status_code', HttpStatusCodeInterface::HTTP_OK);

                    $this->response = ResponseFactory::create($content, $contentType, $status);
                } else {
                    // otherwise assume raw content
                    $this->response = ResponseFactory::create($response);
                }
            }
        }

        return parent::handleEventScriptResult($script, $result);
    }
}
