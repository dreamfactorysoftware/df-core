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
     * @param int     $id
     * @param boolean $protect
     *
     * @return array
     */
    public static function getConfig($id, $protect = true)
    {
        $maps = static::whereServiceId($id);

        if (!empty($maps)) {
            $maps->protectedView = $protect;

            return $maps->toArray();
        } else {
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function validateConfig($config, $create = true)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config)
    {
        // Deleting records using model as oppose to chaining with the where clause.
        // This way forcing model to trigger the 'deleted' event which clears necessary cache.
        // See the boot method above.
        $models = static::whereServiceId($id)->get()->all();
        foreach ($models as $model) {
            $model->delete();
        }
        if (!empty($config)) {
            foreach ($config as $param) {
                //Making sure service_id is the first item in the config.
                //This way service_id will be set first and is available
                //for use right away. This helps setting an auto-generated
                //field that may depend on parent data. See OAuthConfig->setAttribute.
                $param = array_reverse($param, true);
                $param['service_id'] = $id;
                $param = array_reverse($param, true);
                static::create($param);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema =
            [
                'name'        => 'service_event_map',
                'label'       => 'Service Event',
                'description' => 'Select event(s) when you would like this service to fire!',
                'type'        => 'array',
                'required'    => false,
                'allow_null'  => true
            ];
        $schema['items'] = parent::getConfigSchema();

        return $schema;
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
                $schema['type'] = 'grouped_picklist';
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