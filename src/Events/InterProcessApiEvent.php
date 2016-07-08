<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Models\EventScript;
use Log;

abstract class InterProcessApiEvent extends ApiEvent
{
    public function handle()
    {
        $name = $this->makeName();
        Log::debug('API event handled: ' . $name);

        if ($script = $this->getEventScript($name)) {
            $data = $this->makeData();

            if (null !== $result = $this->handleEventScript($script, $data)) {
                return $this->handleEventScriptResult($script, $result);
            }
        }

        return true;
    }
    
    /**
     * @param EventScript $script
     * @param $result
     *
     * @return bool
     */
    protected function handleEventScriptResult($script, $result)
    {
        if (array_get($result, 'stop_propagation', false)) {
            Log::info('  * Propagation stopped by script.');

            return false;
        }

        return parent::handleEventScriptResult($script, $result);
    }
}
