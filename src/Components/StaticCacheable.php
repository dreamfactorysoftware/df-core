<?php
namespace DreamFactory\Core\Components;

use Cache;

/**
 * Class StaticCacheable
 *
 * @package DreamFactory\Core\Components
 */
trait StaticCacheable
{
    /**
     * @type int
     */
    protected static $cacheTTL = 0;

    /**
     * @type string
     */
    protected static $cachePrefix = '';

    /**
     * List of cache keys used to flush service cache.
     * This should be pulled from cache when available.
     * i.e. $cacheKeysMap = ['a','b','c']
     *
     * @var array
     */
    protected static $cacheKeys = [];

    /**
     *
     * @return string The cache prefix associated with this service
     */
    protected static function getCachePrefix()
    {
        return static::$cachePrefix;
    }

    /**
     * @param string|array $keys
     */
    protected static function addKeys($keys)
    {
        static::$cacheKeys =
            array_unique(array_merge((array)static::getCacheKeys(), (array)$keys));

        // Save the keys to cache
        Cache::forever(static::getCachePrefix() . 'cache_keys', static::$cacheKeys);
    }

    /**
     * @param string|array $keys
     */
    protected static function removeKeys($keys)
    {
        static::$cacheKeys = array_diff((array)static::getCacheKeys(), (array)$keys);

        // Save the map to cache
        Cache::forever(static::getCachePrefix() . 'cache_keys', static::$cacheKeys);
    }

    /**
     *
     * @return array The array of cache keys associated with this service
     */
    protected static function getCacheKeys()
    {
        if (empty(static::$cacheKeys)) {
            static::$cacheKeys = Cache::get(static::getCachePrefix() . 'cache_keys', []);
        }

        return (array)static::$cacheKeys;
    }

    /**
     * @param string $name
     *
     * @return array The cache key generated for this service
     */
    protected static function makeCacheKey($name)
    {
        return static::getCachePrefix() . $name;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed The value of cache associated with the given type, id and key
     */
    public static function getFromCache($key, $default = null)
    {
        $fullKey = static::makeCacheKey($key);

        return Cache::get($fullKey, $default);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param bool   $forever
     */
    public static function addToCache($key, $value, $forever = false)
    {
        $fullKey = static::makeCacheKey($key);

        if ($forever) {
            Cache::forever($fullKey, $value);
        } else {
            Cache::put($fullKey, $value, static::$cacheTTL);
        }
        static::addKeys($key);
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public static function removeFromCache($key)
    {
        $fullKey = static::makeCacheKey($key);
        if (!Cache::forget($fullKey)) {
            return false;
        }

        static::removeKeys($key);

        return true;
    }

    /**
     * Forget all keys that we know
     */
    public static function flush()
    {
        $keys = static::getCacheKeys();
        foreach ($keys as $key) {
            Cache::forget(static::makeCacheKey($key));
        }
        static::removeKeys($keys);
    }
}