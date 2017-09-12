<?php

namespace DreamFactory\Core\Components;

use Cache;

/**
 * Class Cacheable
 *
 * @package DreamFactory\Core\Components
 */
trait Cacheable
{
    /**
     * @type int
     */
    protected $cacheTTL = 0;

    /**
     * @type string
     */
    protected $cachePrefix = '';

    /**
     * List of cache keys used to flush service cache.
     * This should be pulled from cache when available.
     * i.e. $cacheKeysMap = ['a','b','c']
     *
     * @var array
     */
    protected $cacheKeys = [];

    protected function setCachePrefix($prefix)
    {
        $this->cachePrefix = $prefix;
    }

    protected function getCachePrefix()
    {
        return $this->cachePrefix;
    }

    /**
     * @param string|array $keys
     */
    protected function addKeys($keys)
    {
        $this->cacheKeys =
            array_unique(array_merge((array)$this->getCacheKeys(), (array)$keys));

        // Save the keys to cache
        Cache::forever($this->getCachePrefix() . 'cache_keys', $this->cacheKeys);
    }

    /**
     * @param string|array $keys
     */
    protected function removeKeys($keys)
    {
        $this->cacheKeys = array_diff((array)$this->getCacheKeys(), (array)$keys);

        // Save the map to cache
        Cache::forever($this->getCachePrefix() . 'cache_keys', $this->cacheKeys);
    }

    /**
     *
     * @return array The array of cache keys associated with this service
     */
    protected function getCacheKeys()
    {
        if (empty($this->cacheKeys)) {
            $this->cacheKeys = Cache::get($this->getCachePrefix() . 'cache_keys', []);
        }

        return (array)$this->cacheKeys;
    }

    /**
     * @param string $name
     *
     * @return string The cache key generated for this service
     */
    protected function makeCacheKey($name)
    {
        return $this->getCachePrefix() . $name;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed The value of cache associated with the given type, id and key
     */
    public function getFromCache($key, $default = null)
    {
        $fullKey = $this->makeCacheKey($key);

        return Cache::get($fullKey, $default);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $forever
     */
    public function addToCache($key, $value, $forever = false)
    {
        $fullKey = $this->makeCacheKey($key);

        if ($forever) {
            Cache::forever($fullKey, $value);
        } else {
            Cache::put($fullKey, $value, $this->cacheTTL);
        }
        $this->addKeys($key);
    }

    /**
     * @param string $key
     * @param mixed  $callback
     * @return mixed The value of cache associated with the given type, id and key
     */
    public function rememberCache($key, $callback)
    {
        $fullKey = $this->makeCacheKey($key);

        $result = Cache::remember($fullKey, $this->cacheTTL, $callback);
        $this->addKeys($key);

        return $result;
    }

    /**
     * @param string $key
     * @param mixed  $callback
     * @return mixed The value of cache associated with the given type, id and key
     */
    public function rememberCacheForever($key, $callback)
    {
        $fullKey = $this->makeCacheKey($key);

        $result = Cache::rememberForever($fullKey, $callback);
        $this->addKeys($key);

        return $result;
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public function removeFromCache($key)
    {
        $fullKey = $this->makeCacheKey($key);
        if (!Cache::forget($fullKey)) {
            return false;
        }

        $this->removeKeys($key);

        return true;
    }

    /**
     * Forget all keys that we know
     */
    public function flush()
    {
        $keys = $this->getCacheKeys();
        foreach ($keys as $key) {
            Cache::forget($this->makeCacheKey($key));
        }
        $this->removeKeys($keys);
    }
}