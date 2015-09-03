<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use DreamFactory\Managed\Providers\ManagedServiceProvider;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if(!config('df.standalone')){
            if(!class_exists(ManagedServiceProvider::class)){
                throw new NotImplementedException('Package not installed. For non-standalone instance df-managed package is required.');
            }
            $this->app->register(ManagedServiceProvider::class);
        }
    }

    public function register()
    {
        $subscriber = new ServiceEventHandler();
        \Event::subscribe($subscriber);
    }
}