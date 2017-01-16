<?php

namespace DreamFactory\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DreamFactory\Core\Services\ServiceManager
 * @see \DreamFactory\Core\Contracts\ServiceInterface
 */
class ServiceManager extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'df.service';
    }
}
