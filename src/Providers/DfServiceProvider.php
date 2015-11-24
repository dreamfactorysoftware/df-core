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
        $subscriber = new ServiceEventHandler();
        \Event::subscribe($subscriber);
    }
}
