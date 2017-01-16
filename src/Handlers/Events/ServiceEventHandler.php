<?php
namespace DreamFactory\Core\Handlers\Events;

use Illuminate\Contracts\Events\Dispatcher;

class ServiceEventHandler
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     */
    public function subscribe($events)
    {
//        $events->listen(
//            [
//                ApiEvent::class,
//            ],
//            static::class . '@handleApiEvent'
//        );
    }
}
