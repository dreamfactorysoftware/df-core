<?php
namespace DreamFactory\Core\Handlers\Events;

use DreamFactory\Core\Events\QueuedApiEvent;
use DreamFactory\Core\Events\ServiceEvent;
use DreamFactory\Core\Models\BaseModel;
use DreamFactory\Core\Services\BaseRestService;
use Illuminate\Contracts\Events\Dispatcher;
use DreamFactory\Core\Models\ServiceEventMap;
use Cache;
use Config;
use Event;
use Log;

class ServiceEventHandler
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     */
    public function subscribe($events)
    {
        $events->listen(
            [
                QueuedApiEvent::class,
            ],
            static::class . '@handleApiEvent'
        );
    }

    /**
     * Handle events.
     *
     * @param QueuedApiEvent $event
     *
     * @return boolean
     */
    public function handleApiEvent($event)
    {
        $eventName = str_replace('.queued', null, $event->name);
        $ckey = 'event:' . $eventName;
        $records = Cache::remember($ckey, Config::get('df.default_cache_ttl'), function () use ($eventName){
            return ServiceEventMap::whereEvent($eventName)->get()->all();
        });
        if (empty($records)) {
            // Look for wildcard events by service (example: user.*)
            $serviceName = substr($eventName, 0, strpos($eventName, '.'));
            $wildcardEvent = $serviceName . '.*';
            $ckey = 'event:' . $wildcardEvent;
            $records = Cache::remember($ckey, Config::get('df.default_cache_ttl'), function () use ($wildcardEvent){
                return ServiceEventMap::whereEvent($wildcardEvent)->get()->all();
            });
        }

        /** @var BaseModel $record */
        foreach ($records as $record) {
            Log::debug('Service event handled: ' . $eventName);
            /** @var BaseRestService $service */
            $service = \ServiceManager::getServiceById($record->service_id);
            Event::fire(new ServiceEvent($service, $event, $record->toArray()));
        }
    }
}
