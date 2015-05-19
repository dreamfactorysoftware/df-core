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
use Illuminate\Routing\Router;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Exceptions\ForbiddenException;
use DreamFactory\Rave\Enums\ServiceRequestorTypes;
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Models\User as DspUser;
use DreamFactory\Rave\Exceptions\NotFoundException;

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
    public static function isAccessAllowed( $requestor = ServiceRequestorTypes::API )
    {
        if ( session( 'is_sys_admin' ) )
        {
            return true;
        }

        /** @var Router $router */
        $router = app( 'router' );
        $service = strtolower( $router->input( 'service' ) );
        $resource = strtolower( $router->input( 'resource' ) );
        $action = VerbsMask::toNumeric( Request::getMethod() );
        $roleServiceAccess = session( 'rsa.role.services' );

        if ( !empty( $roleServiceAccess ) )
        {
            foreach ( $roleServiceAccess as $rsa )
            {
                $allowedResource = ArrayUtils::get( $rsa, 'component' );
                $allowedService = ArrayUtils::get( $rsa, 'service_name' );
                $allowedAction = ArrayUtils::get( $rsa, 'verb_mask' );
                $allowedRequestor = ArrayUtils::get( $rsa, 'requestor_mask' );

                if ( ( $action & $allowedAction ) &&
                     ( $requestor & $allowedRequestor ) &&
                     ( $service === $allowedService || '*' === $allowedService || 'all' === strtolower( $allowedService ) )
                )
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
        if ( !static::isAccessAllowed() )
        {
            throw new ForbiddenException( 'Forbidden. You do not have permission to access the requested service/resource.' );
        }
    }

    /**
     * @param string $action    - REST API action name
     * @param string $service   - API name of the service
     * @param string $component - API component/resource name
     * @param int    $requestor - Entity type requesting the service
     *
     * @throws ForbiddenException
     */
    public static function checkServicePermission( $action, $service, $component = null, $requestor = ServiceRequestorTypes::API )
    {
        $_verb = VerbsMask::toNumeric( static::cleanAction( $action ) );

        $_mask = static::getServicePermissions( $service, $component, $requestor );

        if ( !( $_verb & $_mask ) )
        {
            $msg = ucfirst( $action ) . " access to ";
            if ( !empty( $component ) )
            {
                $msg .= "component '$component' of ";
            }

            $msg .= "service '$service' is not allowed by this user's role.";

            throw new ForbiddenException( $msg );
        }
    }

    /**
     * @param string $service   - API name of the service
     * @param string $component - API component/resource name
     * @param int    $requestor - Entity type requesting the service
     *
     * @returns array
     */
    public static function getServicePermissions( $service, $component = null, $requestor = ServiceRequestorTypes::API )
    {
        if ( session( 'is_sys_admin' ) )
        {
            return VerbsMask::NONE_MASK | VerbsMask::GET_MASK | VerbsMask::POST_MASK | VerbsMask::PUT_MASK | VerbsMask::PATCH_MASK | VerbsMask::DELETE_MASK;
        }

        $_services = ArrayUtils::clean( session( 'rsa.role.services' ) );
        $service = strval( $service );
        $component = strval( $component );

        //  If exact match found take it, otherwise follow up the chain as necessary
        //  All - Service - Component - Sub-component
        $_allAllowed = VerbsMask::NONE_MASK;
        $_allFound = false;
        $_serviceAllowed = VerbsMask::NONE_MASK;
        $_serviceFound = false;
        $_componentAllowed = VerbsMask::NONE_MASK;
        $_componentFound = false;
        $_exactAllowed = VerbsMask::NONE_MASK;
        $_exactFound = false;
        foreach ( $_services as $_svcInfo )
        {
            $_tempRequestors = ArrayUtils::get( $_svcInfo, 'requestor_mask', ServiceRequestorTypes::API );
            if ( !( $requestor & $_tempRequestors ) )
            {
                //  Requestor type not found in allowed requestors, skip access setting
                continue;
            }

            $_tempService = strval( ArrayUtils::get( $_svcInfo, 'service' ) );
            $_tempComponent = strval( ArrayUtils::get( $_svcInfo, 'component' ) );
            if ( null === $_tempVerbs = ArrayUtils::get( $_svcInfo, 'verb_mask' ) )
            {
                //  Check for old verbs array
                if ( null !== $_temp = ArrayUtils::get( $_svcInfo, 'verbs' ) )
                {
                    $_tempVerbs = VerbsMask::arrayToMask( $_temp );
                }
            }

            if ( 0 == strcasecmp( $service, $_tempService ) )
            {
                if ( !empty( $component ) )
                {
                    if ( 0 == strcasecmp( $component, $_tempComponent ) )
                    {
                        // exact match
                        $_exactAllowed |= $_tempVerbs;
                        $_exactFound = true;
                    }
                    elseif ( 0 == strcasecmp( substr( $component, 0, strpos( $component, '/' ) + 1 ) . '*', $_tempComponent ) )
                    {
                        $_componentAllowed |= $_tempVerbs;
                        $_componentFound = true;
                    }
                    elseif ( '*' == $_tempComponent )
                    {
                        $_serviceAllowed |= $_tempVerbs;
                        $_serviceFound = true;
                    }
                }
                else
                {
                    if ( empty( $_tempComponent ) )
                    {
                        // exact match
                        $_exactAllowed |= $_tempVerbs;
                        $_exactFound = true;
                    }
                    elseif ( '*' == $_tempComponent )
                    {
                        $_serviceAllowed |= $_tempVerbs;
                        $_serviceFound = true;
                    }
                }
            }
            else
            {
                // system services don't fall under the "All" services category
                if ( ( 'system' != $service ) && empty( $_tempService ) && ( '*' == $_tempComponent ) )
                {
                    $_allAllowed |= $_tempVerbs;
                    $_allFound = true;
                }
            }
        }

        if ( $_exactFound )
        {
            return $_exactAllowed;
        }
        elseif ( $_componentFound )
        {
            return $_componentAllowed;
        }
        elseif ( $_serviceFound )
        {
            return $_serviceAllowed;
        }
        elseif ( $_allFound )
        {
            return $_allAllowed;
        }

        return VerbsMask::NONE_MASK;
    }

    /**
     * @param string $action - requested REST action
     *
     * @return string
     */
    protected static function cleanAction( $action )
    {
        // check for non-conformists
        $action = strtoupper( $action );
        switch ( $action )
        {
            case 'READ':
                return Verbs::GET;

            case 'CREATE':
                return Verbs::POST;

            case 'UPDATE':
                return Verbs::PUT;
        }

        return $action;
    }

    /**
     * @param string $action
     * @param string $service
     * @param string $component
     *
     * @returns bool
     */
    public static function getServiceFilters( $action, $service, $component = null )
    {
        if ( session( 'is_sys_admin' ) )
        {
            return [ ];
        }

        if ( null === ( $_roleInfo = ArrayUtils::get( static::$_cache, 'role' ) ) )
        {
            // no role assigned
            return [ ];
        }

        $_services = ArrayUtils::clean( ArrayUtils::get( $_roleInfo, 'services' ) );

        $_serviceAllowed = null;
        $_serviceFound = false;
        $_componentFound = false;
        $action = VerbsMask::toNumeric( static::cleanAction( $action ) );

        foreach ( $_services as $_svcInfo )
        {
            $_tempService = ArrayUtils::get( $_svcInfo, 'service' );
            if ( null === $_tempVerbs = ArrayUtils::get( $_svcInfo, 'verb_mask' ) )
            {
                //  Check for old verbs array
                if ( null !== $_temp = ArrayUtils::get( $_svcInfo, 'verbs' ) )
                {
                    $_tempVerbs = VerbsMask::arrayToMask( $_temp );
                }
            }

            if ( 0 == strcasecmp( $service, $_tempService ) )
            {
                $_serviceFound = true;
                $_tempComponent = ArrayUtils::get( $_svcInfo, 'component' );
                if ( !empty( $component ) )
                {
                    if ( 0 == strcasecmp( $component, $_tempComponent ) )
                    {
                        $_componentFound = true;
                        if ( $_tempVerbs & $action )
                        {
                            $_filters = ArrayUtils::get( $_svcInfo, 'filters' );
                            $_operator = ArrayUtils::get( $_svcInfo, 'filter_op', 'AND' );
                            if ( empty( $_filters ) )
                            {
                                return null;
                            }

                            return [ 'filters' => $_filters, 'filter_op' => $_operator ];
                        }
                    }
                    elseif ( empty( $_tempComponent ) || ( '*' == $_tempComponent ) )
                    {
                        if ( $_tempVerbs & $action )
                        {
                            $_filters = ArrayUtils::get( $_svcInfo, 'filters' );
                            $_operator = ArrayUtils::get( $_svcInfo, 'filter_op', 'AND' );
                            if ( empty( $_filters ) )
                            {
                                return null;
                            }

                            $_serviceAllowed = [ 'filters' => $_filters, 'filter_op' => $_operator ];
                        }
                    }
                }
                else
                {
                    if ( empty( $_tempComponent ) || ( '*' == $_tempComponent ) )
                    {
                        if ( $_tempVerbs & $action )
                        {
                            $_filters = ArrayUtils::get( $_svcInfo, 'filters' );
                            $_operator = ArrayUtils::get( $_svcInfo, 'filter_op', 'AND' );
                            if ( empty( $_filters ) )
                            {
                                return null;
                            }

                            $_serviceAllowed = [ 'filters' => $_filters, 'filter_op' => $_operator ];
                        }
                    }
                }
            }
        }

        if ( $_componentFound )
        {
            // at least one service and component match was found, but not the right verb

            return null;
        }
        elseif ( $_serviceFound )
        {
            return $_serviceAllowed;
        }

        return null;
    }

    /**
     * Sets basic info of the user in session when authenticated.
     *
     * @param DspUser $user
     *
     * @return bool
     */
    public static function setUserInfo( DspUser $user )
    {
        if ( \Auth::check() )
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
     * Fetches user session data based on the authenticated user.
     *
     * @return array
     * @throws NotFoundException
     */
    public static function getUserInfo()
    {
        $user = \Auth::user();

        if ( empty( $user ) )
        {
            throw new NotFoundException( 'No user session found.' );
        }

        $sessionData = [
            'user_id'         => $user->id,
            'session_id'      => \Session::getId(),
            'name'            => $user->name,
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'email'           => $user->email,
            'is_sys_admin'    => $user->is_sys_admin,
            'last_login_date' => $user->last_login_date,
            'host'            => gethostname()
        ];

        if ( !$user->is_sys_admin )
        {
            $role = session( 'rsa.role' );
            ArrayUtils::set( $sessionData, 'role', ArrayUtils::get( $role, 'name' ) );
            ArrayUtils::set( $sessionData, 'rold_id', ArrayUtils::get( $role, 'id' ) );
        }

        return $sessionData;
    }

    /**
     * Gets user id of the currently logged in user.
     *
     * @return integer|null
     */
    public static function getCurrentUserId()
    {
        if ( \Auth::check() )
        {
            return session( 'user_id' );
        }

        return null;
    }

    /**
     * Sets System-Role-User lookup keys in session.
     *
     * @param integer|null $roleId
     * @param integer|null $userId
     */
    public static function setLookupKeys( $roleId = null, $userId = null )
    {
        $lookup = LookupKey::getSystemRoleUserLookup( $roleId, $userId );

        \Session::put( 'lookup', ArrayUtils::get( $lookup, 'lookup', [ ] ) );
        \Session::put( 'lookup_secret', ArrayUtils::get( $lookup, 'lookup_secret', [ ] ) );
    }

    /**
     * Sets app lookup keys in session.
     *
     * @param integer|null $appId
     */
    public static function setAppLookupKeys( $appId = null )
    {

        $lookupApp = LookupKey::getAppLookup( $appId );

        \Session::put( 'lookup_app', ArrayUtils::get( $lookupApp, 'lookup', [ ] ) );
        \Session::put( 'lookup_app_secret', ArrayUtils::get( $lookupApp, 'lookup_secret', [ ] ) );
    }
}