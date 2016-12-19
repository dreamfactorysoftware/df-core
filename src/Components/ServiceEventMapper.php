<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Models\ServiceEventMap;

trait ServiceEventMapper
{
    /**
     * @param integer $id
     * @param boolean $protect
     *
     * @return mixed
     */
    public static function getConfig($id, $protect = true)
    {
        $config = parent::getConfig($id, $protect);

        /** @var ServiceEventMap $appRoleMaps */
        $serviceEventMaps = ServiceEventMap::whereServiceId($id)->get();
        $config['service_event_map'] = (empty($serviceEventMaps)) ? [] : $serviceEventMaps->toArray();

        return $config;
    }

    /**
     * @param $id
     * @param $config
     *
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     */
    public static function setConfig($id, $config)
    {
        if (isset($config['service_event_map'])) {
            $maps = $config['service_event_map'];
            if (!is_array($maps)) {
                throw new BadRequestException('Service to Event map must be an array.');
            }
            ServiceEventMap::setConfig($id, $maps);
        }

        parent::setConfig($id, $config);
    }

    /** {@inheritdoc} */
    public static function getConfigSchema()
    {
        $schema = parent::getConfigSchema();
        $schema[] = ServiceEventMap::getConfigSchema();

        return $schema;
    }
}