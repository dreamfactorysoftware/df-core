<?php
namespace DreamFactory\Core;

use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Core\Providers\BaseServiceProvider;

class DfServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/df.php' => config_path(),
            ]
        );
    }

    public function register()
    {
        $subscriber = new ServiceEventHandler();
        \Event::subscribe($subscriber);
    }
}