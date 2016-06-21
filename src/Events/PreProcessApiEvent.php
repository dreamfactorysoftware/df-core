<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Models\EventScript;

class PreProcessApiEvent extends InterProcessApiEvent
{
    protected function makeName()
    {
        return parent::makeName() . '.pre_process';
    }
    
    /**
     * @param EventScript $script
     * @param $result
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
