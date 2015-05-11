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

use \Request;
use DreamFactory\Rave\Exceptions\ForbiddenException;
use DreamFactory\Rave\Enums\ServiceRequestorTypes;
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Models\User as DspUser;
use Illuminate\Routing\Router;

class Session
{
    /**
     * Checks to see if Access is Allowed based on Role-Service-Access.
     *
     * @param int $requestor
     *
     * @return bool
     * @throws \DreamFactory\Rave\Exceptions\NotImplementedException
     */
    public static function isAccessAllowed($requestor = ServiceRequestorTypes::API)
    {
        if(session('is_sys_admin'))
        {
            return true;
        }

        /** @var Router $router */
        $router = app('router');
        $service = $router->input('service');
        $resource = $router->input('resource');
        $action = VerbsMask::toNumeric(Request::getMethod());
        $roleServiceAccess = session('rsa.role.services');

        if(!empty($roleServiceAccess))
        {
            foreach ( $roleServiceAccess as $rsa )
            {
                $allowedResource = ArrayUtils::get( $rsa, 'component' );
                $allowedService = ArrayUtils::get( $rsa, 'service_name' );
                $allowedAction = ArrayUtils::get( $rsa, 'verb_mask' );
                $allowedRequestor = ArrayUtils::get( $rsa, 'requestor_mask' );

                if (
                    ( $action & $allowedAction ) &&
                    ( $requestor & $allowedRequestor ) &&
                    ($service === $allowedService || '*' === $allowedService || 'all' === strtolower($allowedService)) )
                {
                    if ( '*' === $allowedResource || ( $resource === $allowedResource ) )
                    {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks for permission based on Role-Service-Access.
     *
     * @throws ForbiddenException
     */
    public static function checkPermission()
    {
        if(!static::isAccessAllowed())
        {
            throw new ForbiddenException('Forbidden. You do not have permission to access the requested service/resource.');
        }
    }

    /**
     * Sets basic info of the user in session when authenticated.
     *
     * @param DspUser $user
     *
     * @return bool
     */
    public static function setUserInfo(DspUser $user)
    {
        if(\Auth::check())
        {
            \Session::put( 'user_id', $user->id );
            \Session::put( 'display_name', $user->name );
            \Session::put( 'first_name', $user->first_name );
            \Session::put( 'last_name', $user->last_name );
            \Session::put( 'email', $user->email );
            \Session::put( 'is_sys_admin', $user->is_sys_admin );
            \Session::put( 'last_login_date', $user->last_login_date );

            return true;
        }

        return false;
    }

    /**
     * Sets System-Role-User lookup keys in session.
     *
     * @param integer|null $roleId
     * @param integer|null $userId
     */
    public static function setLookupKeys($roleId=null, $userId=null)
    {
        $lookup = LookupKey::getSystemRoleUserLookup( $roleId, $userId );

        \Session::put('lookup', ArrayUtils::get($lookup, 'lookup', []));
        \Session::put('lookup_secret', ArrayUtils::get($lookup, 'lookup_secret', []));
    }

    /**
     * Sets app lookup keys in session.
     *
     * @param integer|null $appId
     */
    public static function setAppLookupKeys($appId=null)
    {

        $lookupApp = LookupKey::getAppLookup( $appId );

        \Session::put('lookup_app', ArrayUtils::get($lookupApp, 'lookup', []));
        \Session::put('lookup_app_secret', ArrayUtils::get($lookupApp, 'lookup_secret', []));
    }
}