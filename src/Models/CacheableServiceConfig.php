<?php
namespace DreamFactory\Core\Models;

/**
 * CacheableServiceConfig
 *
 */
class CacheableServiceConfig extends BaseServiceConfigModel
{
    /**
     * {@inheritdoc}
     */
    public static function getConfig($id, $local_config = null, $protect = true)
    {
        $config = parent::getConfig($id, $local_config, $protect);

        /** @var ServiceCacheConfig $cacheConfig */
        $cacheConfig = ServiceCacheConfig::whereServiceId($id)->first();

        return array_merge($config, $cacheConfig->toArray());
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
}