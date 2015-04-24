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
use \Config;
use \Request;
use DreamFactory\Rave\Models\Role;
use DreamFactory\Rave\Exceptions\UnauthorizedException;
use DreamFactory\Rave\Exceptions\ForbiddenException;
use DreamFactory\Rave\Enums\ServiceRequestorTypes;
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Library\Utility\ArrayUtils;
use Illuminate\Routing\Router;

class Session
{
    public static function isAccessAllowed($requestor = ServiceRequestorTypes::API)
    {
        if(session('rsa.is_sys_admin'))
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

    public static function checkPermission()
    {
        if(!static::isAccessAllowed())
        {
            throw new ForbiddenException('Forbidden. You do not have permission to access the requested service/resource.');
        }
    }

    /**
     * @param int  $roleId
     *
     * @throws UnauthorizedException
     * @throws ForbiddenException
     * @return array
     */
    public static function generateSessionDataFromRole( $roleId )
    {
        static $appFields = array('id', 'name', 'api_key', 'is_active');

        /** @var Role $_role */
        $role = Cache::remember('role_'.$roleId, Config::get('rave.default_cache_ttl'), function() use ($roleId)
            {
                return Role::with(['app_by_role_id', 'role_lookup_by_role_id', 'role_service_access_by_role_id'])->find($roleId);
            }
        );

        if ( empty( $role ) )
        {
            throw new UnauthorizedException( "The role id '$roleId' does not exist in the system." );
        }

        if ( !$role->is_active )
        {
            throw new ForbiddenException( "The role '$role->name' is not currently active." );
        }

        $cached = array();
        $public = array();
        $allowedApps = array();
        $defaultAppId = $role->default_app_id;
        $roleData = array('name' => $role->name, 'id' => $role->id);

        $roleApps = $role->getRelation('app_by_role_id')->toArray();

        if (count($roleApps)>0)
        {
            foreach ( $roleApps as $k => $v )
            {
                if ( 'is_active' === $k && $v == 1 )
                {
                    $allowedApps[$k] = $v;
                }

                if(!in_array($k, $appFields))
                {
                    unset($roleApps[$k]);
                }
            }

            $roleData['app_by_role_id'] = $roleApps;
        }

        $roleData['role_service_access_by_role_id'] = $role->getRelation('role_service_access_by_role_id')->toArray();

        $cached['role'] = $roleData;
        //$cached = array_merge( $cached, LookupKey::getForSession( $_role->id ) );

        return array(
            'cached'         => $cached,
            'public'         => $public,
            'allowed_apps'   => $allowedApps,
            'default_app_id' => $defaultAppId
        );
    }
}