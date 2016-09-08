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
    public static function getConfig($id, $protect = true)
    {
        $config = parent::getConfig($id, $protect);

        $cacheConfig = ServiceCacheConfig::whereServiceId($id)->first();
        $config['cache_enabled'] = (empty($cacheConfig)) ? false : $cacheConfig->getAttribute('cache_enabled');
        $config['cache_ttl'] = (empty($cacheConfig)) ? 0 : $cacheConfig->getAttribute('cache_ttl');

        return $config;
    }

    /**
     * {@inheritdoc}
     */
    public static function setConfig($id, $config)
    {
        $cache = [];
        if (isset($config['cache_enabled'])) {
            $cache['cache_enabled'] = $config['cache_enabled'];
            unset($config['cache_enabled']);
        }
        if (isset($config['cache_ttl'])) {
            $cache['cache_ttl'] = $config['cache_ttl'];
            unset($config['cache_ttl']);
        }
        if (!empty($cache)) {
            ServiceCacheConfig::setConfig($id, $cache);
        }

        parent::setConfig($id, $config);
    }
}