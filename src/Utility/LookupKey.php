<?php

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
    public static function getLookup($roleId = null, $appId = null, $userId = null)
    {
        $lookups = [];
        $secretLookups = [];

        $systemLookups = Lookup::all()->all();
        static::addLookupsToMap($systemLookups, $lookups, $secretLookups);

        $roleLookups = RoleLookup::whereRoleId($roleId)->get()->all();
        static::addLookupsToMap($roleLookups, $lookups, $secretLookups);

        $appLookups = AppLookup::whereAppId($appId)->get()->all();
        static::addLookupsToMap($appLookups, $lookups, $secretLookups);

        $userLookups = UserLookup::whereUserId($userId)->get()->all();
        static::addLookupsToMap($userLookups, $lookups, $secretLookups);

        return [
            'lookup'        => $lookups,
            'lookup_secret' => $secretLookups
        ];
    }

    protected static function addLookupsToMap($lookups, array &$map, array &$map_secret)
    {
        foreach ($lookups as $lookup) {
            if ($lookup->private) {
                $map_secret[$lookup->name] = $lookup->value;
            } else {
                $map[$lookup->name] = $lookup->value;
            }
        }
    }
}