<?php

namespace DreamFactory\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see DreamFactory\Core\Scripting\ScriptEngineManager
 * @see DreamFactory\Core\Contracts\ScriptEngineInterface
 */
class ScriptEngineManager extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'df.script';
    }
}
