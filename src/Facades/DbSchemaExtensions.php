<?php

namespace DreamFactory\Core\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DreamFactory\Core\Components\DbSchemaExtensions
 */
class DbSchemaExtensions extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'df.db.schema';
    }
}
