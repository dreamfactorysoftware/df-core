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
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Enums\ServiceRequestorTypes;
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Rave\Models\Role;
use DreamFactory\Rave\Models\Config as SystemConfig;
use DreamFactory\Rave\Enums\HttpStatusCodes;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;
use Illuminate\Routing\Router;
use Illuminate\Http\Request;
use DreamFactory\Rave\Utility\Session as SessionUtil;

class AccessCheck
{
    protected $config;

    public function __construct()
    {
        $this->config = Cache::remember(
            'system_config',
            Config::get( 'rave.default_cache_ttl' ),
            function ()
            {
                return SystemConfig::instance();
            }
        );
    }

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
        return $next( $request );

//        $pass = false;
//        $this->initSessionValues();
//        $apiKey = $request->query('api_key');
//
//        if(Auth::check())
//        {
//            $authenticatedUser = Auth::user();
//            $roleId = $authenticatedUser->role_id;
//
//            if(!$authenticatedUser->is_active)
//            {
//                return response('User '.$authenticatedUser->email.' is not active.', HttpStatusCodes::HTTP_FORBIDDEN);
//            }
//
//            if($authenticatedUser->is_sys_admin)
//            {
//                $pass = true;
//                Session::put('is_sys_admin', 1);
//            }
//            else if(!empty($roleId))
//            {
//                /** @var Role $role */
//                $role = Role::with(['app_by_role_id', 'role_lookup_by_role_id', 'role_service_access_by_role_id', 'service_by_role_service_access'])->find($roleId);
////                $role = Cache::remember('role_'.$roleId, Config::get('rave.default_cache_ttl'), function() use ($roleId)
////                    {
////                        return Role::with(['app_by_role_id', 'role_lookup_by_role_id', 'role_service_access_by_role_id', 'service_by_role_service_access'])->find($roleId);
////                    }
////                );
//                $roleApps = $role->getRelation('app_by_role_id')->toArray();
//                $roleServiceAccesses = $role->getRoleServiceAccess();
//
//                $roleData = array(
//                    'name' => $role->name,
//                    'id' => $role->id,
//                    'apps' => $roleApps,
//                    'services' => $roleServiceAccesses
//                );
//
//                $pass = static::hasAccess($request, $roleServiceAccesses);
//
//                Session::put('role', $roleData);
//            }
//        }
//        elseif(!empty($apiKey))
//        {
//            if($apiKey === $this->config->api_key)
//            {
//                if(!empty($this->config->global_role_id))
//                {
//                    $pass = true;
//                }
//            }
//        }
//        else{
//            if(!empty($this->config->guest_role_id))
//            {
//                $pass = true;
//            }
//        }
//
//        if(SessionUtil::isAccessAllowed())
//        {
//            return $next($request);
//        }
//        else
//        {
//            return response( 'Unauthorized.', HttpStatusCodes::HTTP_UNAUTHORIZED );
//        }
    }

    protected function initSessionValues()
    {
        Session::put( 'is_sys_admin', 0 );
        Session::put( 'role', array() );
    }
}