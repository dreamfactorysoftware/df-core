<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Models\AppLookup;
use DreamFactory\Core\Models\Lookup;
use DreamFactory\Core\Models\RoleLookup;
use DreamFactory\Core\Models\UserLookup;

class LookupKey
{
    public static function combineLookups($systemLookup = [], $appLookup = [], $roleLookup = [], $userLookup = [])
    {
        $lookup = [];
        $secretLookup = [];

        static::addLookupsToMap(Lookup::class, $systemLookup, $lookup, $secretLookup);
        static::addLookupsToMap(RoleLookup::class, $roleLookup, $lookup, $secretLookup);
        static::addLookupsToMap(AppLookup::class, $appLookup, $lookup, $secretLookup);
        static::addLookupsToMap(UserLookup::class, $userLookup, $lookup, $secretLookup);

        return [
            'lookup'        => $lookup,
            'lookup_secret' => $secretLookup //Actual values of the secret keys. For internal use only.
        ];
    }

    protected static function addLookupsToMap($model, $lookups, array &$map, array &$mapSecret)
    {
        foreach ($lookups as $lookup) {
            if ($lookup['private']) {
                $secretLookup = $model::find($lookup['id']);
                $mapSecret[$lookup['name']] = $secretLookup->value;
            } else {
                $map[$lookup['name']] = $lookup['value'];
            }
        }
    }
}