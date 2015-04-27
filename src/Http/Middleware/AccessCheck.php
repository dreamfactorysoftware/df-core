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
use DreamFactory\Rave\Enums\ContentTypes;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\ForbiddenException;
use DreamFactory\Rave\Exceptions\UnauthorizedException;
use DreamFactory\Rave\Utility\ResponseFactory;
use DreamFactory\Rave\Models\App;
use DreamFactory\Rave\Models\Role;
use DreamFactory\Rave\Utility\Session as SessionUtil;

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
        if(empty($apiKey))
        {
            $apiKey = $request->header('X_DREAMFACTORY_API_KEY');
        }

        $authenticated = Auth::check();

        //If not authenticated then check for HTTP Basic Auth request.
        if(!$authenticated)
        {
            Auth::onceBasic();
            $authenticated = Auth::check();
        }

        if($authenticated)
        {
            $authenticatedUser = Auth::user();

            if ( !$authenticatedUser->is_active )
            {
                return static::getException(new ForbiddenException('User ' . $authenticatedUser->email . ' is not active.'), $request);
            }
        }

        if($authenticated && $authenticatedUser->is_sys_admin)
        {
            Session::put( 'rsa.is_sys_admin', 1 );
        }
        else if(!empty($apiKey) && $authenticated && class_exists('\DreamFactory\Rave\User\Resources\System\User'))
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

                if(empty($app))
                {
                    return static::getException(new UnauthorizedException('Unauthorized request. Invalid API Key.'), $request);
                }

                /** @var Role $role */
                $role = $app->getRelation( 'role_by_user_to_app_role' )->first();

                if(empty($role))
                {
                    return static::getException(new UnauthorizedException('Unauthorized request. User to App-Role not found.'), $request);
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

                if(empty($app))
                {
                    return static::getException(new UnauthorizedException('Unauthorized request. Invalid API Key.'), $request);
                }

                /** @var Role $role */
                $role = $app->getRelation( 'role_by_role_id' );

                if(empty($role))
                {
                    return static::getException(new UnauthorizedException('Unauthorized request. Role not found.'), $request);
                }

                $roleData = static::getRoleData( $role );
                Cache::put($cacheKey, $roleData, Config::get('rave.default_cache_ttl'));
            }

            Session::put('rsa.role', $roleData);
        }
        else{
            $basicAuthUser = $request->getUser();
            if(!empty($basicAuthUser))
            {
                return static::getException(new UnauthorizedException('Unauthorized. User credential did not match.'), $request);
            }

            return static::getException(new BadRequestException('Bad request. Missing api key.'), $request);
        }

        if(SessionUtil::isAccessAllowed())
        {
            return $next($request);
        }
        else
        {
            return static::getException(new ForbiddenException('Access Forbidden.'), $request);
        }
    }

    /**
     * Initiates the session variable.
     */
    protected static function initSessionValues()
    {
        Session::put( 'rsa', array() );
    }

    /**
     * Generates the role data array using the role model.
     *
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
     * Generates the cache key.
     *
     * @param      $apiKey
     * @param null $userId
     *
     * @return string
     */
    protected static function getCacheKey($apiKey, $userId=null)
    {
        return $apiKey.$userId;
    }

    /**
     * @param \Exception $e
     * @param \Illuminate\Http\Request $request
     *
     * @return array|mixed|string
     */
    protected static function getException($e, $request)
    {
        $response =  ResponseFactory::create( $e, ContentTypes::PHP_OBJECT, $e->getCode() );

        $accept = explode(',', $request->header('ACCEPT'));

        return ResponseFactory::sendResponse($response, $accept);
    }

}