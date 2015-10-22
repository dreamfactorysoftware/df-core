<?php

namespace DreamFactory\Core\Models;

use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Lookup
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Lookup whereId($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereName($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereIsPrivate($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereLastModifiedDate($value)
 */
class Lookup extends BaseSystemLookup
{
    protected $table = 'system_lookup';

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (Lookup $lookup){
                \Cache::forget('system_lookups');
            }
        );

        static::deleted(
            function (Lookup $lookup){
                \Cache::forget('system_lookups');
            }
        );
    }

    /**
     * Returns system lookups cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getCachedLookups($key = null, $default = null)
    {
        $cacheKey = 'system_lookups';
        try {
            $result = \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function (){
                return Lookup::all()->toArray();
            });

            if (is_null($result)) {
                return $default;
            }
        } catch (ModelNotFoundException $ex) {
            return $default;
        }

        if (is_null($key)) {
            return $result;
        }

        return (isset($result[$key]) ? $result[$key] : $default);
    }

}