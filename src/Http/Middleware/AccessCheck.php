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
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Enums\ContentTypes;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\ForbiddenException;
use DreamFactory\Rave\Exceptions\UnauthorizedException;
use DreamFactory\Rave\Utility\ResponseFactory;
use DreamFactory\Rave\Models\App;
use DreamFactory\Rave\Models\Role;
use DreamFactory\Rave\Models\User;
use DreamFactory\Rave\Utility\Session as SessionUtil;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Utility\Cache as CacheUtil;

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
        Session::put( 'is_sys_admin', 0 );

        //Bypassing access check for admin login attempts using system/admin/session (POST)
        if ( static::isAdminLogin() )
        {
            return $next( $request );
        }

        //Check to see if session ID is supplied for using an existing session.
        $sessionId = $request->header( 'X_DREAMFACTORY_SESSION_TOKEN' );

        if ( !empty( $sessionId ) && !Auth::check() )
        {
            if ( Session::isValidId( $sessionId ) )
            {
                Session::setId( $sessionId );
                Session::start();
                \Request::setSession( Session::driver() );
            }
        }

        $apiKey = $request->query( 'api_key' );
        if ( empty( $apiKey ) )
        {
            $apiKey = $request->header( 'X_DREAMFACTORY_API_KEY' );
        }

        $authenticated = Auth::check();

        //If not authenticated then check for HTTP Basic Auth request.
        if ( !$authenticated )
        {
            Auth::onceBasic();
            $authenticated = Auth::check();
        }

        if ( $authenticated )
        {
            /** @var User $authenticatedUser */
            $authenticatedUser = Auth::user();
        }

        if ( $authenticated && $authenticatedUser->is_sys_admin )
        {
            $appId = null;
            if ( $apiKey )
            {
                $app = App::whereApiKey( $apiKey )->first();
                $appId = $app->id;
            }
            Session::put( 'is_sys_admin', 1 );
            SessionUtil::setLookupKeys( null, $authenticatedUser->id );
            SessionUtil::setAppLookupKeys( $appId );
        }
        else if ( !empty( $apiKey ) && $authenticated && class_exists( '\DreamFactory\Rave\User\Resources\System\User' ) )
        {
            $cacheKey = CacheUtil::getApiKeyUserCacheKey( $apiKey, $authenticatedUser->id );

            $cacheData = Cache::get( $cacheKey );

            $roleData = ( !empty( $cacheData ) ) ? ArrayUtils::get( $cacheData, 'role_data' ) : [ ];

            if ( empty( $roleData ) )
            {
                /** @var App $app */
                $app = App::with(
                    [
                        'role_by_user_to_app_to_role' => function ( $q ) use ( $authenticatedUser )
                        {
                            $q->whereUserId( $authenticatedUser->id );
                        }
                    ]
                )->whereApiKey( $apiKey )->first();

                if ( empty( $app ) )
                {
                    return static::getException( new UnauthorizedException( 'Unauthorized request. Invalid API Key.' ), $request );
                }

                /** @var Role $role */
                $role = $app->getRelation( 'role_by_user_to_app_to_role' )->first();

                if ( empty( $role ) )
                {
                    $app->load( 'role_by_role_id' );
                    /** @var Role $role */
                    $role = $app->getRelation( 'role_by_role_id' );
                }

                if ( empty( $role ) )
                {
                    return static::getException( new InternalServerErrorException( 'Unexpected error occurred. Role not found for Application.' ), $request );
                }

                $roleData = static::getRoleData( $role );
                $cacheData = [
                    'role_data' => $roleData,
                    'user_id'   => $authenticatedUser->id,
                    'app_id'    => $app->id
                ];
                Cache::put( $cacheKey, $cacheData, Config::get( 'rave.default_cache_ttl' ) );
            }

            Session::put( 'rsa.role', $roleData );
            SessionUtil::setLookupKeys( ArrayUtils::get( $roleData, 'id' ), ArrayUtils::get( $cacheData, 'user_id' ) );
            SessionUtil::setAppLookupKeys( ArrayUtils::get( $cacheData, 'app_id' ) );

        }
        elseif ( !empty( $apiKey ) )
        {
            $cacheKey = CacheUtil::getApiKeyUserCacheKey( $apiKey );

            $cacheData = Cache::get( $cacheKey );

            $roleData = ( !empty( $cacheData ) ) ? ArrayUtils::get( $cacheData, 'role_data' ) : [ ];

            if ( empty( $roleData ) )
            {
                /** @var App $app */
                $app = App::with( 'role_by_role_id' )->whereApiKey( $apiKey )->first();

                if ( empty( $app ) )
                {
                    return static::getException( new UnauthorizedException( 'Unauthorized request. Invalid API Key.' ), $request );
                }

                /** @var Role $role */
                $role = $app->getRelation( 'role_by_role_id' );

                if ( empty( $role ) )
                {
                    return static::getException( new InternalServerErrorException( 'Unexpected error occurred. Role not found for Application.' ), $request );
                }

                $roleData = static::getRoleData( $role );
                $cacheData = [
                    'role_data' => $roleData,
                    'app_id'    => $app->id
                ];
                Cache::put( $cacheKey, $cacheData, Config::get( 'rave.default_cache_ttl' ) );
            }

            Session::put( 'rsa.role', $roleData );
            SessionUtil::setLookupKeys( ArrayUtils::get( $roleData, 'id' ) );
            SessionUtil::setAppLookupKeys( ArrayUtils::get( $cacheData, 'app_id' ) );
        }
        else
        {
            $basicAuthUser = $request->getUser();
            if ( !empty( $basicAuthUser ) )
            {
                return static::getException( new UnauthorizedException( 'Unauthorized. User credential did not match.' ), $request );
            }

            return static::getException( new BadRequestException( 'Bad request. Missing api key.' ), $request );
        }

        if ( SessionUtil::isAccessAllowed() )
        {
            return $next( $request );
        }
        else
        {
            return static::getException( new ForbiddenException( 'Access Forbidden.' ), $request );
        }
    }

    /**
     * Generates the role data array using the role model.
     *
     * @param Role $role
     *
     * @return array
     */
    protected static function getRoleData( Role $role )
    {
        $role->load( 'role_service_access_by_role_id', 'service_by_role_service_access' );
        $rsa = $role->getRoleServiceAccess();

        $roleData = array(
            'name'     => $role->name,
            'id'       => $role->id,
            'services' => $rsa
        );

        return $roleData;
    }

    /**
     * @param \Exception               $e
     * @param \Illuminate\Http\Request $request
     *
     * @return array|mixed|string
     */
    protected static function getException( $e, $request )
    {
        $response = ResponseFactory::create( $e, ContentTypes::PHP_OBJECT, $e->getCode() );

        $accept = explode( ',', $request->header( 'ACCEPT' ) );

        return ResponseFactory::sendResponse( $response, $accept );
    }

    /**
     * Checks to see if it is an admin user login call.
     *
     * @return bool
     * @throws \DreamFactory\Rave\Exceptions\NotImplementedException
     */
    protected static function isAdminLogin()
    {
        /** @var Router $router */
        $router = app( 'router' );
        $service = strtolower( $router->input( 'service' ) );
        $resource = strtolower( $router->input( 'resource' ) );
        $action = VerbsMask::toNumeric( \Request::getMethod() );

        if ( ( $action & VerbsMask::POST_MASK ) && $service === 'system' && $resource === 'admin/session' )
        {
            return true;
        }
        else
        {
            return false;
        }
    }
}