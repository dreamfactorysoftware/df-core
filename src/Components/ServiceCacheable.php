<?php

namespace DreamFactory\Core\Components;

/**
 * Class ServiceCacheable
 *
 * @package DreamFactory\Core\Components
 */
trait ServiceCacheable
{
    use Cacheable {
        Cacheable::getFromCache as getFromCacheOld;
        Cacheable::addToCache as addToCacheOld;
        Cacheable::removeFromCache as removeFromCacheOld;
        Cacheable::rememberCache as rememberCacheOld;
        Cacheable::rememberCacheForever as rememberCacheForeverOld;
    }

    /**
     * @type string
     */
    protected $configPrefix = '';

    protected function setConfigBasedCachePrefix($prefix)
    {
        $this->configPrefix = $prefix;
    }

    protected function getConfigBasedCachePrefix()
    {
        return $this->configPrefix;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed The value of cache associated with the given type, id and key
     */
    public function getFromCache($key, $default = null)
    {
        $key = $this->getConfigBasedCachePrefix() . $key;

        return $this->getFromCacheOld($key, $default);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $forever
     */
    public function addToCache($key, $value, $forever = false)
    {
        $key = $this->getConfigBasedCachePrefix() . $key;
        $this->addToCacheOld($key, $value, $forever);
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public function removeFromCache($key)
    {
        $key = $this->getConfigBasedCachePrefix() . $key;

        return $this->removeFromCacheOld($key);
    }

    /**
     * @param string $key
     * @param mixed $callback
     *
     * @return mixed
     */
    public function rememberCache($key, $callback)
    {
        $key = $this->getConfigBasedCachePrefix() . $key;

        return $this->rememberCacheOld($key, $callback);
    }

    /**
     * @param string $key
     * @param mixed $callback
     *
     * @return mixed
     */
    public function rememberCacheForever($key, $callback)
    {
        $key = $this->getConfigBasedCachePrefix() . $key;

        return $this->rememberCacheForeverOld($key, $callback);
    }
}