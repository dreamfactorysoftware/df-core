<?php

namespace DreamFactory\Core\Components;

use Cache;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;

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
        $keys = (array)$keys;
        if (empty(array_diff($keys, $this->getCacheKeys()))) {
            return;
        }

        $this->updateKeys(function ($current) use ($keys) {
            return array_unique(array_merge($current, $keys));
        });
    }

    /**
     * @param string|array $keys
     */
    protected function removeKeys($keys)
    {
        $this->updateKeys(function ($current) use ($keys) {
            return array_diff($current, (array)$keys);
        });
    }

    /**
     * Read-modify-write of the key index, under a lock when the store supports one.
     *
     * @param callable $fn receives the fresh key list, returns the new one
     */
    private function updateKeys(callable $fn)
    {
        $write = function () use ($fn) {
            $this->cacheKeys = array_values($fn($this->getCacheKeys()));
            Cache::forever($this->getCachePrefix() . 'cache_keys', $this->cacheKeys);
        };

        if (Cache::getStore() instanceof LockProvider) {
            try {
                Cache::lock($this->getCachePrefix() . 'cache_keys_lock', 10)->block(5, $write);

                return;
            } catch (LockTimeoutException $e) {
                // fall through to the unlocked write
            }
        }
        // ponytail: unlocked fallback = old race, only on lockless stores / lock timeout
        $write();
    }

    /**
     *
     * @return array The array of cache keys associated with this service
     */
    protected function getCacheKeys()
    {
        $this->cacheKeys = Cache::get($this->getCachePrefix() . 'cache_keys', []);

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
        // clear the index first so a key re-cached mid-flush gets re-indexed
        $this->removeKeys($keys);
        foreach ($keys as $key) {
            Cache::forget($this->makeCacheKey($key));
        }
    }
}