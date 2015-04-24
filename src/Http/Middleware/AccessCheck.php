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

namespace DreamFactory\Rave\Http\Middleware;

use \Auth;
use \Cache;
use \Config;
use \Closure;
use \Session;
use DreamFactory\Rave\Models\App;
use DreamFactory\Rave\Models\Role;
use DreamFactory\Rave\Enums\HttpStatusCodes;
use DreamFactory\Rave\Utility\Session as SessionUtil;
use DreamFactory\Rave\User\Resources\System\User as UserManagement;

class AccessCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle( $request, Closure $next )
    {
        //return $next($request);

        static::initSessionValues();
        $apiKey = $request->query('api_key');
        $authenticated = Auth::check();

        if($authenticated)
        {
            $authenticatedUser = Auth::user();

            if ( !$authenticatedUser->is_active )
            {
                return response( 'User ' . $authenticatedUser->email . ' is not active.', HttpStatusCodes::HTTP_FORBIDDEN );
            }
        }

        if($authenticated && $authenticatedUser->is_sys_admin)
        {
            Session::put( 'rsa.is_sys_admin', 1 );
        }
        else if(!empty($apiKey) && $authenticated && class_exists(UserManagement::class))
        {
            $cacheKey = static::getCacheKey($apiKey, $authenticatedUser->id);

            $roleData = Cache::get($cacheKey);

            if(empty($roleData))
            {
                /** @var App $app */
                $app = App::with(
                    [
                        'role_by_user_to_app_role' => function ( $q ) use ( $authenticatedUser )
                        {
                            $q->whereUserId( $authenticatedUser->id );
                        }
                    ]
                )->whereApiKey( $apiKey )->first();

                /** @var Role $role */
                $role = $app->getRelation( 'role_by_user_to_app_role' )->first();

                if(empty($role))
                {
                    return response('Unauthorized request. Access denied.', HttpStatusCodes::HTTP_UNAUTHORIZED);
                }

                $roleData = static::getRoleData( $role );
                Cache::put( $cacheKey, $roleData, Config::get( 'rave.default_cache_ttl' ) );
            }

            Session::put('rsa.role', $roleData);
        }
        elseif(!empty($apiKey))
        {
            $cacheKey = static::getCacheKey($apiKey);

            $roleData = Cache::get($cacheKey);

            if(empty($roleData))
            {
                /** @var App $app */
                $app = App::with( 'role_by_role_id' )->whereApiKey( $apiKey )->first();

                /** @var Role $role */
                $role = $app->getRelation( 'role_by_role_id' );

                if(empty($role))
                {
                    return response('Unauthorized request. Access denied.', HttpStatusCodes::HTTP_UNAUTHORIZED);
                }

                $roleData = static::getRoleData( $role );
                Cache::put($cacheKey, $roleData, Config::get('rave.default_cache_ttl'));
            }

            Session::put('rsa.role', $roleData);
        }
        else{
            return response( 'Bad request. Missing api key.', HttpStatusCodes::HTTP_BAD_REQUEST );
        }

        if(SessionUtil::isAccessAllowed())
        {
            return $next($request);
        }
        else
        {
            return response( 'Access Forbidden.', HttpStatusCodes::HTTP_FORBIDDEN );
        }
    }

    protected static function initSessionValues()
    {
        Session::put( 'rsa', array() );
    }

    /**
     * @param Role $role
     *
     * @return array
     */
    protected static function getRoleData(Role $role)
    {
        $role->load('role_service_access_by_role_id', 'service_by_role_service_access');
        $rsa = $role->getRoleServiceAccess();

        $roleData = array(
            'name' => $role->name,
            'id' => $role->id,
            'services' => $rsa
        );

        return $roleData;
    }

    /**
     * @param      $apiKey
     * @param null $userId
     *
     * @return string
     */
    protected static function getCacheKey($apiKey, $userId=null)
    {
        return $apiKey.$userId;
    }
}