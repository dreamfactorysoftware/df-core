<?php

namespace DreamFactory\Core\Handlers\Events;

use DreamFactory\Core\Events\ApiEvent;
use DreamFactory\Core\Events\BaseServiceEvent;
use DreamFactory\Core\Events\ServiceDeletedEvent;
use DreamFactory\Core\Events\ServiceAssignedEvent;
use DreamFactory\Core\Events\ServiceModifiedEvent;
use DreamFactory\Core\Models\BaseModel;
use DreamFactory\Core\Models\ServiceEventMap;
use DreamFactory\Core\Services\BaseRestService;
use Cache;
use Config;
use Event;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Events\QueryExecuted;
use Log;
use ServiceManager;

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
                ApiEvent::class,
            ],
            static::class . '@handleApiEvent'
        );
        $events->listen(
            [
                ServiceModifiedEvent::class,
                ServiceDeletedEvent::class,
            ],
            static::class . '@handleServiceChangeEvent'
        );
        if (config('df.db.query_log_enabled')) {
            $events->listen(
                [
                    QueryExecuted::class,
                ],
                static::class . '@handleQueryExecutedEvent'
            );
        }
    }

    /**
     * Handle API events.
     *
     * @param ApiEvent $event
     */
    public function handleApiEvent($event)
    {
        $eventName = str_replace('.queued', null, $event->name);
        $ckey = 'event:' . $eventName;
        $records = Cache::remember($ckey, Config::get('df.default_cache_ttl'), function () use ($eventName) {
            return ServiceEventMap::whereEvent($eventName)->get()->all();
        });
        if (empty($records)) {
            // Look for wildcard events by service (example: user.*)
            $serviceName = substr($eventName, 0, strpos($eventName, '.'));
            $wildcardEvent = $serviceName . '.*';
            $ckey = 'event:' . $wildcardEvent;
            $records = Cache::remember($ckey, Config::get('df.default_cache_ttl'), function () use ($wildcardEvent) {
                return ServiceEventMap::whereEvent($wildcardEvent)->get()->all();
            });
        }

        /** @var BaseModel $record */
        foreach ($records as $record) {
            Log::debug('Service event handled: ' . $eventName);
            /** @var BaseRestService $service */
            $service = \ServiceManager::getServiceById($record->service_id);
            Event::dispatch(new ServiceAssignedEvent($service, $event, $record->toArray()));
        }
    }

    /**
     * Handle service change events.
     *
     * @param BaseServiceEvent $event
     */
    public function handleServiceChangeEvent($event)
    {
        ServiceManager::purge($event->service->name); // clear out any old config
    }

    public function handleQueryExecutedEvent($event)
    {
        Log::debug($event->connectionName . ': ' . $event->sql . ': ' . $event->time);
    }
}
