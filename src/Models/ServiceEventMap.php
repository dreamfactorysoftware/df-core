<?php
namespace DreamFactory\Core\Models;

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

        switch ($schema['name']) {
            case 'event':
                $schema['label'] = 'Event';
                $schema['type'] = 'event_picklist';
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