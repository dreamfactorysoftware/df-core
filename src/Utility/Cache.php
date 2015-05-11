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


class Cache
{
    public static function getApiKeyUserCacheKey($apiKey, $userId=null)
    {
        return $apiKey.$userId;
    }

    public static function getSystemLookupCacheKey()
    {
        return 'lookup_system';
    }

    public static function getRoleLookupCacheKey($roleId=null)
    {
        return 'lookup_role_'.$roleId;
    }

    public static function getUserLookupCacheKey($userId=null)
    {
        return 'lookup_user_'.$userId;
    }

    public static function getAppLookupCacheKey($appId=null)
    {
        return 'lookup_app_'.$appId;
    }
}