<?php

namespace DreamFactory\Core\Utility;

class LookupKey
{
    public static function combineLookups($systemLookup = [], $appLookup = [], $roleLookup = [], $userLookup = [])
    {
        $lookup = [];
        $secretLookup = [];

        static::addLookupsToMap($systemLookup, $lookup, $secretLookup);
        static::addLookupsToMap($roleLookup, $lookup, $secretLookup);
        static::addLookupsToMap($appLookup, $lookup, $secretLookup);
        static::addLookupsToMap($userLookup, $lookup, $secretLookup);

        return [
            'lookup' => $lookup,
            'lookup_secret' => $secretLookup
        ];
    }

    protected static function addLookupsToMap($lookups, array &$map, array &$map_secret)
    {
        foreach ($lookups as $lookup) {
            if ($lookup['private']) {
                $map_secret[$lookup['name']] = $lookup['value'];
            } else {
                $map[$lookup['name']] = $lookup['value'];
            }
        }
    }
}