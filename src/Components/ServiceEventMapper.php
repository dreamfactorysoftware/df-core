<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\ServiceEventMap;

trait ServiceEventMapper
{
    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $config = parent::getConfig($id, $local_config, $protect);

        $serviceEventMaps = ServiceEventMap::whereServiceId($id)->get();
        $maps = [];
        /** @var ServiceEventMap $map */
        foreach ($serviceEventMaps as $map) {
            $map->protectedView = $protect;
            $maps[] = $map->toArray();
        }
        $config['service_event_map'] = $maps;

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $local_config = null)
    {
        if (isset($config['service_event_map'])) {
            $maps = $config['service_event_map'];
            if (!is_array($maps)) {
                throw new BadRequestException('Service to Event map must be an array.');
            }

            // Deleting records using model as oppose to chaining with the where clause.
            // This way forcing model to trigger the 'deleted' event which clears necessary cache.
            // See the boot method in ServiceEventMap::class.
            $models = ServiceEventMap::whereServiceId($id)->get()->all();
            foreach ($models as $model) {
                $model->delete();
            }
            if (!empty($maps)) {
                foreach ($maps as $map) {
                    ServiceEventMap::setConfig($id, $map, $local_config);
                }
            }
        }

        return parent::setConfig($id, $config, $local_config);
    }

    /**
     * {@inheritdoc}
     */
    public static function storeConfig($id, $config)
    {
        if (isset($config['service_event_map'])) {
            $maps = $config['service_event_map'];
            if (!is_array($maps)) {
                throw new BadRequestException('Service to event map must be an array.');
            }

            // Deleting records using model as oppose to chaining with the where clause.
            // This way forcing model to trigger the 'deleted' event which clears necessary cache.
            // See the boot method in ServiceEventMap::class.
            $models = ServiceEventMap::whereServiceId($id)->get()->all();
            foreach ($models as $model) {
                $model->delete();
            }
            if (!empty($maps)) {
                foreach ($maps as $map) {
                    ServiceEventMap::storeConfig($id, $map);
                }
            }
        }

        parent::storeConfig($id, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $schema[] = [
            'name'        => 'service_event_map',
            'label'       => 'Service Event',
            'description' => 'Select event(s) to be used by this service.',
            'type'        => 'array',
            'required'    => false,
            'allow_null'  => true,
            'items'       => ServiceEventMap::getConfigSchema(),
        ];

        return $schema;
    }
}