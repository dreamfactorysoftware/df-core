<?php
namespace DreamFactory\Core\Handlers\Events;

use DreamFactory\Core\Events\ApiEvent;
use DreamFactory\Core\Events\PostProcessApiEvent;
use DreamFactory\Core\Events\PreProcessApiEvent;
use DreamFactory\Core\Events\QueuedApiEvent;
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
                PreProcessApiEvent::class,
                PostProcessApiEvent::class,
                QueuedApiEvent::class,
            ],
            static::class . '@handleApiEvent'
        );
    }

    /**
     * Handle events.
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
