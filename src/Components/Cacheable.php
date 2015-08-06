<?php
namespace DreamFactory\Core\Components;

use \Cache;
use DreamFactory\Library\Utility\ArrayUtils;

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

    /**
     * @param string|array $keys
     */
    protected function addKeys($keys)
    {
        $this->cacheKeys =
            array_unique(array_merge(ArrayUtils::clean($this->getCacheKeys()), ArrayUtils::clean($keys)));

        // Save the keys to cache
        Cache::forever($this->cachePrefix . 'cache_keys', $this->cacheKeys);
    }

    /**
     * @param string|array $keys
     */
    protected function removeKeys($keys)
    {
        $this->cacheKeys = array_diff(ArrayUtils::clean($this->getCacheKeys()), ArrayUtils::clean($keys));

        // Save the map to cache
        Cache::forever($this->cachePrefix . 'cache_keys', $this->cacheKeys);
    }

    /**
     *
     * @return array The array of cache keys associated with this service
     */
    protected function getCacheKeys()
    {
        if (empty($this->cacheKeys)) {
            $this->cacheKeys = Cache::get($this->cachePrefix . 'cache_keys', []);
        }

        return ArrayUtils::clean($this->cacheKeys);
    }

    /**
     * @param string $name
     *
     * @return array The cache key generated for this service
     */
    protected function makeCacheKey($name)
    {
        return $this->cachePrefix . $name;
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

            if ($forever){
                Cache::forever($fullKey, $value);
            }
            else {
                Cache::put($fullKey, $value, $this->cacheTTL);
            }
            $this->addKeys($key);
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