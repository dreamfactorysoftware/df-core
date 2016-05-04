<?php

namespace DreamFactory\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see DreamFactory\Core\Resources\System\SystemResourceManager
 * @see DreamFactory\Core\Contracts\SystemResourceInterface
 */
class SystemResourceManager extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'df.system.resource';
    }
}
