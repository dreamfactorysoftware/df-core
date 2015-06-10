<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Utility;

use \Cache;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Models\App;

/**
 * Class CacheUtilities
 *
 * @package DreamFactory\Rave\Utility
 */
class CacheUtilities
{
    /**
     * Map of API key to its App id.
     * This should be pulled from cache when available.
     *
     * @var array
     */
    protected static $apiKeyAppIdMap = [ ];

    /**
     * Map of API key and optional User id to a Role id.
     * This should be pulled from cache when available.
     *
     * @var array
     */
    protected static $apiKeyUserIdRoleIdMap = [ ];

    /**
     * Map of resource id to a list of cache keys, i.e. role_id.
     * This should be pulled from cache when available.
     * i.e. $cacheKeysMap = ['role' => [1 => ['a','b','c']]]
     *
     * @var array
     */
    protected static $cacheKeysMap = [ ];

    public static function flush()
    {
        Cache::flush();
        static::$apiKeyAppIdMap = [ ];
        static::$apiKeyUserIdRoleIdMap = [ ];
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed The value of cache associated with the given key
     */
    public static function get( $key, $default = null )
    {
        return Cache::get( $key, $default );
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     *
     * @return bool
     */
    public static function add( $key, $value, $ttl = null )
    {
        if ( is_null( $ttl ) )
        {
            $ttl = \Config::get( 'rave.default_cache_ttl' );
        }

        return Cache::add( $key, $value, $ttl );
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     */
    public static function put( $key, $value, $ttl = null )
    {
        if ( is_null( $ttl ) )
        {
            $ttl = \Config::get( 'rave.default_cache_ttl' );
        }

        Cache::put( $key, $value, $ttl );
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    public static function forever( $key, $value )
    {
        Cache::forever( $key, $value );
    }

    /**
     * @param string $key
     *
     * @return boolean
     */
    public static function forget( $key )
    {
        return Cache::forget( $key );
    }

    /**
     * @param      $apiKey
     * @param null $userId
     *
     * @return string
     */
    public static function makeApiKeyUserIdKey( $apiKey, $userId = null )
    {
        return $apiKey . $userId;
    }

    /**
     * @return string
     */
    public static function getSystemLookupCacheKey()
    {
        return 'lookup_system';
    }

    /**
     * @param null $roleId
     *
     * @return string
     */
    public static function getRoleLookupCacheKey( $roleId = null )
    {
        return 'lookup_role_' . $roleId;
    }

    /**
     * @param null $userId
     *
     * @return string
     */
    public static function getUserLookupCacheKey( $userId = null )
    {
        return 'lookup_user_' . $userId;
    }

    /**
     * @param null $appId
     *
     * @return string
     */
    public static function getAppLookupCacheKey( $appId = null )
    {
        return 'lookup_app_' . $appId;
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     *
     * @param string $api_key
     *
     * @return int The app id
     */
    public static function getAppIdByApiKey( $api_key )
    {
        if ( !empty( $api_key ) )
        {
            if ( empty( static::$apiKeyAppIdMap ) )
            {
                static::$apiKeyAppIdMap = Cache::get( 'apiKeyAppIdMap', [ ] );
            }

            if ( isset( static::$apiKeyAppIdMap[$api_key] ) )
            {
                return static::$apiKeyAppIdMap[$api_key];
            }
            else
            {
                $app = App::whereApiKey( $api_key )->first();
                if ( $app )
                {
                    return $app->id;
                }
            }
        }

        return null;
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     * Once session is established, the role id is accessible via Session.
     *
     * @param string   $api_key
     * @param null|int $user_id
     *
     * @return null|int The role id or null for admin
     */
    public static function getRoleIdByApiKeyAndUserId( $api_key, $user_id = null )
    {
        if ( empty( static::$apiKeyUserIdRoleIdMap ) )
        {
            static::$apiKeyUserIdRoleIdMap = Cache::get( 'apiKeyUserIdRoleIdMap', [ ] );
        }

        if ( isset( static::$apiKeyUserIdRoleIdMap[$api_key], static::$apiKeyUserIdRoleIdMap[$api_key][$user_id] ) )
        {
            return static::$apiKeyUserIdRoleIdMap[$api_key][$user_id];
        }

        return null;
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string|array   $keys
     */
    public static function addKeysByTypeAndId( $type, $id, $keys )
    {
        if ( empty( static::$cacheKeysMap ) )
        {
            static::$cacheKeysMap = Cache::get( 'cacheKeysMap', [ ] );
        }

        $newKeys = ArrayUtils::clean( $keys );
        if ( isset( static::$cacheKeysMap[$type], static::$cacheKeysMap[$type][$id] ) )
        {
            $oldKeys = ArrayUtils::clean( static::$cacheKeysMap[$type][$id] );
            $newKeys = array_unique( array_merge( $oldKeys, $newKeys ) );
        }

        static::$cacheKeysMap[$type][$id] = $newKeys;
        // Save the map to cache
        Cache::put( 'cacheKeysMap', static::$cacheKeysMap, \Config::get( 'rave.default_cache_ttl' ) );
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string|array   $keys
     */
    public static function removeKeysByTypeAndId( $type, $id, $keys )
    {
        if ( empty( static::$cacheKeysMap ) )
        {
            static::$cacheKeysMap = Cache::get( 'cacheKeysMap', [ ] );
        }

        $newKeys = [ ];
        if ( isset( static::$cacheKeysMap[$type], static::$cacheKeysMap[$type][$id] ) )
        {
            $oldKeys = ArrayUtils::clean( static::$cacheKeysMap[$type][$id] );
            $newKeys = array_diff( $oldKeys, ArrayUtils::clean( $keys ) );
        }

        static::$cacheKeysMap[$type][$id] = $newKeys;
        // Save the map to cache
        Cache::put( 'cacheKeysMap', static::$cacheKeysMap, \Config::get( 'rave.default_cache_ttl' ) );
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     *
     * @return array The array of cache keys associated with the given type and id
     */
    public static function getKeysByTypeAndId( $type, $id = null )
    {
        if ( empty( static::$cacheKeysMap ) )
        {
            static::$cacheKeysMap = Cache::get( 'cacheKeysMap', [ ] );
        }

        if ( isset( static::$cacheKeysMap[$type], static::$cacheKeysMap[$type][$id] ) )
        {
            return static::$cacheKeysMap[$type][$id];
        }

        return [ ];
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $name
     *
     * @return array The cache key generated from the given type and id
     */
    public static function makeKeyFromTypeAndId( $type, $id, $name )
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
    public static function getByTypeAndId( $type, $id, $key, $default = null )
    {
        $key = static::makeKeyFromTypeAndId( $type, $id, $key );

        return Cache::get( $key, $default );
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
    public static function addByTypeAndId( $type, $id, $key, $value, $ttl = null )
    {
        $key = static::makeKeyFromTypeAndId( $type, $id, $key );
        if ( is_null( $ttl ) )
        {
            $ttl = \Config::get( 'rave.default_cache_ttl' );
        }

        if ( !Cache::add( $key, $value, $ttl ) )
        {
            return false;
        }

        static::addKeysByTypeAndId( $type, $id, $key );

        return true;
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $key
     * @param mixed          $value
     * @param /DateTime|int  $ttl
     */
    public static function putByTypeAndId( $type, $id, $key, $value, $ttl = null )
    {
        $key = static::makeKeyFromTypeAndId( $type, $id, $key );
        if ( is_null( $ttl ) )
        {
            $ttl = \Config::get( 'rave.default_cache_ttl' );
        }

        Cache::put( $key, $value, $ttl );
        static::addKeysByTypeAndId( $type, $id, $key );
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     * @param string         $key
     *
     * @return boolean
     */
    public static function forgetByTypeAndId( $type, $id, $key )
    {
        $key = static::makeKeyFromTypeAndId( $type, $id, $key );
        if ( !Cache::forget( $key ) )
        {
            return false;
        }

        static::removeKeysByTypeAndId( $type, $id, $key );

        return true;
    }

    /**
     * @param string         $type
     * @param null|int|mixed $id
     *
     * @return boolean
     */
    public static function forgetAllByTypeAndId( $type, $id )
    {
        $keys = static::getKeysByTypeAndId( $type, $id );
        foreach ( $keys as $key )
        {
            Cache::forget( $key );
        }

        static::removeKeysByTypeAndId( $type, $id, $keys );

        return true;
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed The value of cache keys associated with the given type and id
     */
    public static function getByRoleId( $id, $name, $default = null )
    {
        return static::getByTypeAndId( 'role', $id, $name, $default );
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     *
     * @return boolean
     */
    public static function addByRoleId( $id, $name, $value, $ttl = null )
    {
        return static::addByTypeAndId( 'role', intval( $id ), $name, $value, $ttl );
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     */
    public static function putByRoleId( $id, $name, $value, $ttl = null )
    {
        static::putByTypeAndId( 'role', intval( $id ), $name, $value, $ttl );
    }

    /**
     * @param int    $id
     * @param string $name
     *
     * @return boolean
     */
    public static function forgetByRoleId( $id, $name )
    {
        return static::forgetByTypeAndId( 'role', intval( $id ), $name );
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $default
     *
     * @return mixed The value of cache keys associated with the given type and id
     */
    public static function getByServiceId( $id, $name, $default = null )
    {
        return static::getByTypeAndId( 'service', $id, $name, $default );
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     *
     * @return boolean
     */
    public static function addByServiceId( $id, $name, $value, $ttl = null )
    {
        return static::addByTypeAndId( 'service', intval( $id ), $name, $value, $ttl );
    }

    /**
     * @param int    $id
     * @param string $name
     * @param mixed  $value
     * @param /DateTime|int  $ttl
     */
    public static function putByServiceId( $id, $name, $value, $ttl = null )
    {
        static::putByTypeAndId( 'service', intval( $id ), $name, $value, $ttl );
    }

    /**
     * @param int    $id
     * @param string $name
     *
     * @return boolean
     */
    public static function forgetByServiceId( $id, $name )
    {
        return static::forgetByTypeAndId( 'service', intval( $id ), $name );
    }

    /**
     * @param null|int $id
     *
     * @return array The array of cache keys associated with the given app id
     */
    public static function getKeysByAppId( $id = null )
    {
        return static::getKeysByTypeAndId( 'app', $id );
    }

    /**
     * @param null|int $id
     *
     * @return array The array of cache keys associated with the given role id
     */
    public static function getKeysByRoleId( $id = null )
    {
        return static::getKeysByTypeAndId( 'role', $id );
    }

    /**
     * @param null|int $id
     *
     * @return null|array The array of cache keys associated with the given service id
     */
    public static function getKeysByServiceId( $id = null )
    {
        return static::getKeysByTypeAndId( 'service', $id );
    }

    /**
     * @param null|int $id
     *
     * @return null|array The array of cache keys associated with the given user id
     */
    public static function getKeysByUserId( $id = null )
    {
        return static::getKeysByTypeAndId( 'user', $id );
    }
}