<?php

namespace DreamFactory\Core\Providers;

use Illuminate\Support\ServiceProvider;

abstract class BaseServiceProvider extends ServiceProvider
{
    public function mergeConfig($packageConfigFile, $appConfigKey)
    {
        if (file_exists($packageConfigFile)) {
            $packageConfig = $this->app['files']->getRequire($packageConfigFile);
            $appConfig = $this->app['config']->get($appConfigKey);
            $config = array_merge_recursive($packageConfig, $appConfig);
            $this->app['config']->set($appConfigKey, $config);

            return true;
        }

        return false;
    }
}