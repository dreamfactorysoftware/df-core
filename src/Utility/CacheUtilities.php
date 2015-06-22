<?php

namespace DreamFactory\Core\Utility;

use \Cache;
use \Config;
use DreamFactory\Core\Models\Lookup;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\User;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Models\App;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class CacheUtilities
 *
 * @package DreamFactory\Core\Utility
 */
class CacheUtilities
{
    /**
     * Map of resource id to a list of cache keys, i.e. role_id.
     * This should be pulled from cache when available.
     * i.e. $cacheKeysMap = ['role' => [1 => ['a','b','c']]]
     *
     * @var array
     */
    protected static $cacheKeysMap = [];

    public static function flush()
    {
        Cache::flush();
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed The value of cache associated with the given key
     */
    public static function get($key, $default = null)
    {
        return Cache::get($key, $default);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     *
     * @return bool
     */
    public static function add($key, $value, $ttl = null)
    {
        if (is_null($ttl)) {
            $ttl = Config::get('df.default_cache_ttl');
        }

        return Cache::add($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     */
    public static function put($key, $value, $ttl = null)
    {
        if (is_null($ttl)) {
            $ttl = Config::get('df.default_cache_ttl');
        }

        Cache::put($key, $value, $ttl);
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public static function forever($key, $value)
    {
        Cache::forever($key, $value);
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public static function forget($key)
    {
        return Cache::forget($key);
    }

    /**
     * Returns user info cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param int         $id
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getUserInfo($id, $key = null, $default = null)
    {
        $cacheKey = 'user:' . $id;
        try {
            $result = Cache::remember($cacheKey, Config::get('df.default_cache_ttl'), function () use ($id){
                return User::with('user_lookup_by_user_id')->findOrFail($id)->toArray();
            });

            if (is_null($result)) {
                return $default;
            }
        } catch (ModelNotFoundException $ex) {
            return $default;
        }

        if (is_null($key)) {
            return $result;
        }

        return ArrayUtils::get($result, $key, $default);
    }

    /**
     * Returns role info cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param int         $id
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getRoleInfo($id, $key = null, $default = null)
    {
        $cacheKey = 'role:' . $id;
        try {
            $result = Cache::remember($cacheKey, Config::get('df.default_cache_ttl'), function () use ($id){
                return Role::with(['role_lookup_by_role_id', 'role_service_access_by_role_id'])
                    ->findOrFail($id)
                    ->toArray();
            });

            if (is_null($result)) {
                return $default;
            }
        } catch (ModelNotFoundException $ex) {
            return $default;
        }

        if (is_null($key)) {
            return $result;
        }

        return ArrayUtils::get($result, $key, $default);
    }

    /**
     * Returns app info cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param int         $id
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getAppInfo($id, $key = null, $default = null)
    {
        $cacheKey = 'app:' . $id;
        try {
            $result = Cache::remember($cacheKey, Config::get('df.default_cache_ttl'), function () use ($id){
                return App::with('app_lookup_by_app_id')->findOrFail($id)->toArray();
            });

            if (is_null($result)) {
                return $default;
            }
        } catch (ModelNotFoundException $ex) {
            return $default;
        }

        if (is_null($key)) {
            return $result;
        }

        return ArrayUtils::get($result, $key, $default);
    }

    /**
     * Returns system lookups cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getSystemLookups($key = null, $default = null)
    {
        $cacheKey = 'system_lookups';
        try {
            $result = Cache::remember($cacheKey, Config::get('df.default_cache_ttl'), function (){
                return Lookup::all()->toArray();
            });

            if (is_null($result)) {
                return $default;
            }
        } catch (ModelNotFoundException $ex) {
            return $default;
        }

        if (is_null($key)) {
            return $result;
        }

        return ArrayUtils::get($result, $key, $default);
    }

    /**
     * @param string   $apiKey
     * @param int|null $userId
     *
     * @return string
     */
    public static function makeApiKeyUserIdKey($apiKey, $userId = null)
    {
        return $apiKey . $userId;
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     *
     * @param string $api_key
     * @param int    $app_id
     */
    public static function setApiKeyToAppId($api_key, $app_id)
    {
        $cacheKey = 'apikey2appid:' . $api_key;
        Cache::put($cacheKey, $app_id, Config::get('df.default_cache_ttl'));
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     *
     * @param string $api_key
     *
     * @return int The app id
     */
    public static function getAppIdByApiKey($api_key)
    {
        $cacheKey = 'apikey2appid:' . $api_key;
        try {
            return Cache::remember($cacheKey, Config::get('df.default_cache_ttl'), function () use ($api_key){
                return App::whereApiKey($api_key)->firstOrFail()->id;
            });
        } catch (ModelNotFoundException $ex) {
            return null;
        }
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     *
     * @param int $id
     *
     * @return string|null The API key for the designated app or null if not found
     */
    public static function getApiKeyByAppId($id)
    {
        if (!empty($id)) {
            // use local app caching
            $key = static::getAppInfo($id, 'api_key', null);
            if (!is_null($key)) {
                static::setApiKeyToAppId($key, $id);
            }
        }

        return null;
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string|array   $keys
     */
    public static function addKeysByTypeAndId($type, $id, $keys)
    {
        if (empty(static::$cacheKeysMap)) {
            static::$cacheKeysMap = Cache::get('cacheKeysMap', []);
        }

        $newKeys = ArrayUtils::clean($keys);
        if (isset(static::$cacheKeysMap[$type], static::$cacheKeysMap[$type][$id])) {
            $oldKeys = ArrayUtils::clean(static::$cacheKeysMap[$type][$id]);
            $newKeys = array_unique(array_merge($oldKeys, $newKeys));
        }

        static::$cacheKeysMap[$type][$id] = $newKeys;
        // Save the map to cache
        Cache::put('cacheKeysMap', static::$cacheKeysMap, Config::get('df.default_cache_ttl'));
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string|array   $keys
     */
    public static function removeKeysByTypeAndId($type, $id, $keys)
    {
        if (empty(static::$cacheKeysMap)) {
            static::$cacheKeysMap = Cache::get('cacheKeysMap', []);
        }

        $newKeys = [];
        if (isset(static::$cacheKeysMap[$type], static::$cacheKeysMap[$type][$id])) {
            $oldKeys = ArrayUtils::clean(static::$cacheKeysMap[$type][$id]);
            $newKeys = array_diff($oldKeys, ArrayUtils::clean($keys));
        }

        static::$cacheKeysMap[$type][$id] = $newKeys;
        // Save the map to cache
        Cache::put('cacheKeysMap', static::$cacheKeysMap, Config::get('df.default_cache_ttl'));
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     *
     * @return array The array of cache keys associated with the given type and id
     */
    public static function getKeysByTypeAndId($type, $id = null)
    {
        if (empty(static::$cacheKeysMap)) {
            static::$cacheKeysMap = Cache::get('cacheKeysMap', []);
        }

        if (isset(static::$cacheKeysMap[$type], static::$cacheKeysMap[$type][$id])) {
            return static::$cacheKeysMap[$type][$id];
        }

        return [];
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $name
     *
     * @return array The cache key generated from the given type and id
     */
    public static function makeKeyFromTypeAndId($type, $id, $name)
    {
        return $type . $id . ':' . $name;
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $key
     * @param mixed          $default
     *
     * @return mixed The value of cache associated with the given type, id and key
     */
    public static function getByTypeAndId($type, $id, $key, $default = null)
    {
        $key = static::makeKeyFromTypeAndId($type, $id, $key);

        return Cache::get($key, $default);
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $key
     * @param mixed          $value
     * @param /DateTime|int  $ttl
     *
     * @return boolean
     */
    public static function addByTypeAndId($type, $id, $key, $value, $ttl = null)
    {
        $key = static::makeKeyFromTypeAndId($type, $id, $key);
        if (is_null($ttl)) {
            $ttl = Config::get('df.default_cache_ttl');
        }

        if (!Cache::add($key, $value, $ttl)) {
            return false;
        }

        static::addKeysByTypeAndId($type, $id, $key);

        return true;
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $key
     * @param mixed          $value
     * @param /DateTime|int  $ttl
     */
    public static function putByTypeAndId($type, $id, $key, $value, $ttl = null)
    {
        $key = static::makeKeyFromTypeAndId($type, $id, $key);
        if (is_null($ttl)) {
            $ttl = Config::get('df.default_cache_ttl');
        }

        Cache::put($key, $value, $ttl);
        static::addKeysByTypeAndId($type, $id, $key);
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $key
     *
     * @return boolean
     */
    public static function forgetByTypeAndId($type, $id, $key)
    {
        $key = static::makeKeyFromTypeAndId($type, $id, $key);
        if (!Cache::forget($key)) {
            return false;
        }

        static::removeKeysByTypeAndId($type, $id, $key);

        return true;
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     *
     * @return boolean
     */
    public static function forgetAllByTypeAndId($type, $id)
    {
        $keys = static::getKeysByTypeAndId($type, $id);
        foreach ($keys as $key) {
            Cache::forget($key);
        }

        static::removeKeysByTypeAndId($type, $id, $keys);

        return true;
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed The value of cache keys associated with the given type and id
     */
    public static function getByRoleId($id, $name, $default = null)
    {
        return static::getByTypeAndId('role', $id, $name, $default);
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     *
     * @return boolean
     */
    public static function addByRoleId($id, $name, $value, $ttl = null)
    {
        return static::addByTypeAndId('role', intval($id), $name, $value, $ttl);
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     */
    public static function putByRoleId($id, $name, $value, $ttl = null)
    {
        static::putByTypeAndId('role', intval($id), $name, $value, $ttl);
    }

    /**
     * @param int    $id
     * @param string $name
     *
     * @return boolean
     */
    public static function forgetByRoleId($id, $name)
    {
        return static::forgetByTypeAndId('role', intval($id), $name);
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed The value of cache keys associated with the given type and id
     */
    public static function getByServiceId($id, $name, $default = null)
    {
        return static::getByTypeAndId('service', $id, $name, $default);
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     *
     * @return boolean
     */
    public static function addByServiceId($id, $name, $value, $ttl = null)
    {
        return static::addByTypeAndId('service', intval($id), $name, $value, $ttl);
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     */
    public static function putByServiceId($id, $name, $value, $ttl = null)
    {
        static::putByTypeAndId('service', intval($id), $name, $value, $ttl);
    }

    /**
     * @param int    $id
     * @param string $name
     *
     * @return boolean
     */
    public static function forgetByServiceId($id, $name)
    {
        return static::forgetByTypeAndId('service', intval($id), $name);
    }

    /**
     * @param null|int $id
     *
     * @return array The array of cache keys associated with the given app id
     */
    public static function getKeysByAppId($id = null)
    {
        return static::getKeysByTypeAndId('app', $id);
    }

    /**
     * @param null|int $id
     *
     * @return array The array of cache keys associated with the given role id
     */
    public static function getKeysByRoleId($id = null)
    {
        return static::getKeysByTypeAndId('role', $id);
    }

    /**
     * @param null|int $id
     *
     * @return null|array The array of cache keys associated with the given service id
     */
    public static function getKeysByServiceId($id = null)
    {
        return static::getKeysByTypeAndId('service', $id);
    }

    /**
     * @param null|int $id
     *
     * @return null|array The array of cache keys associated with the given user id
     */
    public static function getKeysByUserId($id = null)
    {
        return static::getKeysByTypeAndId('user', $id);
    }
}