<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\ServiceResponse;
use DreamFactory\Library\Utility\Enums\Verbs;
use ServiceManager;
use Cache;

class ServiceEventMap extends BaseServiceConfigModel
{
    /** @var string */
    protected $table = 'service_event_map';

    /** @var array */
    protected $fillable = ['service_id', 'event', 'data'];

    /** @var array */
    protected $casts = [
        'id'         => 'integer',
        'service_id' => 'integer'
    ];

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var bool
     */
    public $incrementing = true;

    /**
     * @var bool
     */
    public static $alwaysNewOnSet = true;

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (ServiceEventMap $map){
                $key = 'event:' . $map->event;
                Cache::forget($key);
            }
        );

        static::deleted(
            function (ServiceEventMap $map){
                $key = 'event:' . $map->event;
                Cache::forget($key);
            }
        );
    }

    /**
     * @param array $schema
     */
    protected static function prepareConfigSchemaField(array &$schema)
    {
        parent::prepareConfigSchemaField($schema);

        $eventList = [];

        switch ($schema['name']) {
            case 'event':
                /** @var ServiceResponse $response */
                $response = ServiceManager::handleRequest('system', Verbs::GET, 'event', ['as_list' => 1]);
                $events = ResourcesWrapper::unwrapResources($response->getContent());
                $temp = [];
                foreach ($events as $event) {
                    $service = substr($event, 0, strpos($event, '.'));
                    if(!isset($temp[$service])) {
                        $temp[$service] = [];
                    }
                    $temp[$service][] = [
                        'label' => $event,
                        'name'  => $event
                    ];
                }
                foreach ($temp as $service => $items){
                    array_unshift($items, ['label' => 'All ' . $service . ' events', 'name' => $service . '.*']);
                    $eventList[] = [
                        'label' => $service,
                        'name'  => $service,
                        'items' => $items
                    ];
                }
                $schema['label'] = 'Event';
                $schema['type'] = 'event_picklist';
                $schema['values'] = $eventList;
                $schema['description'] = 'Select an Event.';
                $schema['allow_null'] = false;
                break;
            case 'data':
                $schema['label'] = 'Payload data';
                $schema['description'] = 'Payload data for related service.';
                break;
        }
    }
}