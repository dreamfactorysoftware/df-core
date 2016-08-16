<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Models\EventScript;

class PreProcessApiEvent extends InterProcessApiEvent
{
    public $request;

    /**
     * Create a new event instance.
     *
     * @param string                  $path
     * @param ServiceRequestInterface $request
     * @param mixed                   $resource
     */
    public function __construct($path, &$request, $resource = null)
    {
        $this->request = $request;
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
        }

        return parent::handleEventScriptResult($script, $result);
    }
}
