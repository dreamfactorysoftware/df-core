<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Managed\Providers\ManagedServiceProvider;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
{
    public function register()
    {
        if (config('df.managed')) {
            if (!class_exists(ManagedServiceProvider::class)) {
                throw new NotImplementedException('Package not installed. For managed instance df-managed package is required.');
            }
            $this->app->register(ManagedServiceProvider::class);
        }

        $subscriber = new ServiceEventHandler();
        \Event::subscribe($subscriber);
    }
}