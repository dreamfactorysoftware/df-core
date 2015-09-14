<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that caches stuff, that can be cleared
 */
interface CacheInterface
{
    public function addToCache($key, $value, $forever = false);
    public function getFromCache($key, $default = null);
    public function removeFromCache($key);
    public function flush();
}
