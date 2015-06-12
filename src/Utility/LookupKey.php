<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Models\AppLookup;
use DreamFactory\Core\Models\Lookup;
use DreamFactory\Core\Models\RoleLookup;
use DreamFactory\Core\Models\UserLookup;

class LookupKey
{
    /**
     * @param null|int $roleId
     * @param null|int $appId
     * @param null|int $userId
     *
     * @return array
     */
    public static function getLookup( $roleId = null, $appId = null, $userId = null )
    {
        $lookups = [ ];
        $secretLookups = [ ];

        $systemLookups = Lookup::all()->all();
        static::addLookupsToMap( $systemLookups, $lookups, $secretLookups );

        $roleLookups = RoleLookup::whereRoleId( $roleId )->get()->all();
        static::addLookupsToMap( $roleLookups, $lookups, $secretLookups );

        $appLookups = AppLookup::whereAppId( $appId )->get()->all();
        static::addLookupsToMap( $appLookups, $lookups, $secretLookups );

        $userLookups = UserLookup::whereUserId( $userId )->get()->all();
        static::addLookupsToMap( $userLookups, $lookups, $secretLookups );

        return [
            'lookup'        => $lookups,
            'lookup_secret' => $secretLookups
        ];
    }

    protected static function addLookupsToMap( $lookups, array &$map, array &$map_secret )
    {
        foreach ( $lookups as $lookup )
        {
            if ( $lookup->private )
            {
                $map_secret[$lookup->name] = $lookup->value;
            }
            else
            {
                $map[$lookup->name] = $lookup->value;
            }
        }
    }
}