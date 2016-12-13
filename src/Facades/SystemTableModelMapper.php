<?php

namespace DreamFactory\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DreamFactory\Core\Models\SystemTableModelMapper
 */
class SystemTableModelMapper extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'df.system.table_model_map';
    }
}
