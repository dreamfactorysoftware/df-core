<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Providers\CorsServiceProvider;
use DreamFactory\Core\Models\CorsConfig;
use Cache;

/**
 * Class Cors
 *
 * Configures the System's CORS settings.
 *
 * @package DreamFactory\Core\Resources\System
 */
class Cors extends BaseSystemResource
{
    /**
     * @var string DreamFactory\Core\Models\BaseSystemModel Model Class name.
     */
    protected static $model = CorsConfig::class;

    protected function handlePOST()
    {
        Cache::forget(CorsServiceProvider::CACHE_KEY);

        return parent::handlePOST();
    }

    protected function handleDELETE()
    {
        Cache::forget(CorsServiceProvider::CACHE_KEY);

        return parent::handleDELETE();
    }

    protected function handlePUT()
    {
        Cache::forget(CorsServiceProvider::CACHE_KEY);

        return parent::handlePUT();
    }

    protected function handlePATCH()
    {
        Cache::forget(CorsServiceProvider::CACHE_KEY);

        return parent::handlePATCH();
    }
}