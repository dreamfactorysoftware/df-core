<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Models\ServiceCacheConfig;

trait SupportsCache
{
    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $config = parent::getConfig($id, $local_config, $protect);
        if ($cacheConfig = ServiceCacheConfig::whereServiceId($id)->first()) {
            $config = array_merge($config, $cacheConfig->toArray());
        }

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config, $local_config = null)
    {
        ServiceCacheConfig::setConfig($id, $config, $local_config);

        return parent::setConfig($id, $config, $local_config);
    }

    /**
     * {@inheritdoc}
     */
    public static function storeConfig($id, $config)
    {
        ServiceCacheConfig::storeConfig($id, $config);

        return parent::storeConfig($id, $config);
    }

    /**
     * {@inheritdoc}
     */
    public static function getConfigSchema()
    {
        $schema = (array)parent::getConfigSchema();
        $schema = array_merge($schema, ServiceCacheConfig::getConfigSchema());

        return $schema;
    }
}