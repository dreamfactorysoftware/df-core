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
        // PHP 8.5 + Laravel 13 unserialize-time autoload race fix.
        // Many service caches store typed objects (TableSchema, ColumnSchema,
        // RelationSchema, NamedResourceSchema, FunctionSchema, ProcedureSchema,
        // ParameterSchema, RoutineSchema, Eloquent\Collection). On PHP 8.5
        // the cache backend's unserialize() can fire before the autoloader
        // has loaded those classes, returning __PHP_Incomplete_Class. Force
        // PSR-4 autoload up front so the rehydrated objects are real.
        class_exists(\DreamFactory\Core\Database\Schema\NamedResourceSchema::class);
        class_exists(\DreamFactory\Core\Database\Schema\TableSchema::class);
        class_exists(\DreamFactory\Core\Database\Schema\ColumnSchema::class);
        class_exists(\DreamFactory\Core\Database\Schema\RelationSchema::class);
        class_exists(\DreamFactory\Core\Database\Schema\FunctionSchema::class);
        class_exists(\DreamFactory\Core\Database\Schema\ProcedureSchema::class);
        class_exists(\DreamFactory\Core\Database\Schema\ParameterSchema::class);
        class_exists(\DreamFactory\Core\Database\Schema\RoutineSchema::class);

        $fullKey = $this->makeCacheKey($key);
        $value = Cache::get($fullKey, $default);

        // Defensive: detect __PHP_Incomplete_Class anywhere in the result and
        // drop the cache entry so the next caller will recompute. Walks one
        // level deep into arrays (the typical shape — `tables` map etc.).
        if ($this->cacheValueIsIncomplete($value)) {
            Cache::forget($fullKey);
            return $default;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @return bool true if value is or contains a PHP_Incomplete_Class
     */
    protected function cacheValueIsIncomplete($value)
    {
        if ($value instanceof \__PHP_Incomplete_Class) {
            return true;
        }
        if (is_array($value)) {
            foreach ($value as $entry) {
                if ($entry instanceof \__PHP_Incomplete_Class) {
                    return true;
                }
            }
        }
        return false;
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