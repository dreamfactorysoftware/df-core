<?php
namespace DreamFactory\Core\Handlers\Events;

use DreamFactory\Core\Events\ApiEvent;
use DreamFactory\Core\Events\ResourcePreProcess;
use DreamFactory\Core\Events\ResourcePostProcess;
use DreamFactory\Core\Events\ServicePreProcess;
use DreamFactory\Core\Events\ServicePostProcess;
use Illuminate\Contracts\Events\Dispatcher;

class ServiceEventHandler
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     *
     * @return array
     */
    public function subscribe($events)
    {
        $events->listen(
            [
                ServicePreProcess::class,
                ServicePostProcess::class,
                ResourcePreProcess::class,
                ResourcePostProcess::class
            ],
            static::class . '@handleApiEvent'
        );
    }

    /**
     * Handle service pre-process events.
     *
     * @param ApiEvent $event
     *
     * @return bool
     */
    public function handleApiEvent($event)
    {
        $event->handle();
    }
}
