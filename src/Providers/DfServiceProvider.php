<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
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