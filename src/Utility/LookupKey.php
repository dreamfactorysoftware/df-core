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

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Models\AppLookup;
use DreamFactory\Rave\Models\Lookup;
use DreamFactory\Rave\Models\RoleLookup;
use DreamFactory\Rave\Models\UserLookup;
use DreamFactory\Rave\Utility\Cache as CacheUtil;

class LookupKey
{
    /**
     * @param null $roleId
     * @param null $appId
     * @param null $userId
     *
     * @return array
     */
    public static function getLookup( $roleId = null, $appId=null, $userId = null )
    {
        $slck = CacheUtil::getSystemLookupCacheKey();
        $rlck = CacheUtil::getRoleLookupCacheKey($roleId);
        $alck = CacheUtil::getAppLookupCacheKey($appId);
        $ulck = CacheUtil::getUserLookupCacheKey($userId);

        $systemKeys = \Cache::get($slck);
        $roleKeys = \Cache::get($rlck);
        $appKeys = \Cache::get($alck);
        $userKeys = \Cache::get($ulck);

        if(empty($systemKeys))
        {
            $systemLookup = Lookup::all()->all();
            $systemKeys = static::modelsToArray($systemLookup);
            \Cache::put($slck, $systemKeys, \Config::get('rave.default_cache_ttl'));
        }

        if(empty($roleKeys))
        {
            $roleLookup = RoleLookup::whereRoleId( $roleId )->get()->all();
            $roleKeys = static::modelsToArray($roleLookup);
            \Cache::put($rlck, $roleKeys, \Config::get('rave.default_cache_ttl'));
        }

        if(empty($appKeys))
        {
            $appLookup = AppLookup::whereAppId( $appId )->get()->all();
            $appKeys = static::modelsToArray($appLookup);
            \Cache::put($alck, $appKeys, \Config::get('rave.default_cache_ttl'));
        }

        if(empty($userKeys))
        {
            $userLookup = UserLookup::whereUserId( $userId )->get()->all();
            $userKeys = static::modelsToArray($userLookup);
            \Cache::put($ulck, $userKeys, \Config::get('rave.default_cache_ttl'));
        }

        $lookup = [ ];
        $lookupSecret = [ ];

        foreach ( $systemKeys as $sk )
        {
            if ( true === ArrayUtils::getBool($sk, 'private') )
            {
                ArrayUtils::set( $lookupSecret, ArrayUtils::get($sk, 'name'), ArrayUtils::get($sk, 'value') );
            }
            else
            {
                ArrayUtils::set( $lookup, ArrayUtils::get($sk, 'name'), ArrayUtils::get($sk, 'value') );
            }
        }

        foreach ( $roleKeys as $rk )
        {
            if ( true === ArrayUtils::getBool($rk, 'private') )
            {
                ArrayUtils::set( $lookupSecret, ArrayUtils::get($rk, 'name'), ArrayUtils::get($rk, 'value') );
            }
            else
            {
                ArrayUtils::set( $lookup, ArrayUtils::get($rk, 'name'), ArrayUtils::get($rk, 'value') );
            }
        }

        foreach ( $appKeys as $ak )
        {
            if ( true === ArrayUtils::getBool($ak, 'private') )
            {
                ArrayUtils::set( $lookupSecret, ArrayUtils::get($ak, 'name'), ArrayUtils::get($ak, 'value'));
            }
            else
            {
                ArrayUtils::set( $lookup, ArrayUtils::get($ak, 'name'), ArrayUtils::get($ak, 'value') );
            }
        }

        foreach ( $userKeys as $uk )
        {
            if ( true === ArrayUtils::getBool($uk, 'private') )
            {
                ArrayUtils::set( $lookupSecret, ArrayUtils::get($uk, 'name'), ArrayUtils::get($uk, 'value') );
            }
            else
            {
                ArrayUtils::set( $lookup, ArrayUtils::get($uk, 'name'), ArrayUtils::get($uk, 'value') );
            }
        }

        return [
            'lookup'        => $lookup,
            'lookup_secret' => $lookupSecret
        ];
    }

    /**
     * @param $models
     *
     * @return array
     */
    protected static function modelsToArray($models)
    {
        $array = [];

        foreach($models as $m)
        {
            $array[] = [
                'name' => $m->name,
                'value' => $m->value,
                'private' => $m->private
            ];
        }

        return $array;
    }
}