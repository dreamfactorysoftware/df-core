<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Utility\Session;
use Illuminate\Database\Query\Builder;

/**
 * BaseSystemLookup - an abstract base class for system lookups
 *
 * @property integer $id
 * @property integer $app_id
 * @property integer $role_id
 * @property integer $user_id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static Builder|Lookup whereId($value)
 * @method static Builder|Lookup whereAppId($value)
 * @method static Builder|Lookup whereRoleId($value)
 * @method static Builder|Lookup whereUserId($value)
 * @method static Builder|Lookup whereName($value)
 * @method static Builder|Lookup whereValue($value)
 * @method static Builder|Lookup whereDescription($value)
 * @method static Builder|Lookup whereIsPrivate($value)
 * @method static Builder|Lookup whereCreatedDate($value)
 * @method static Builder|Lookup whereLastModifiedDate($value)
 */
class BaseSystemLookup extends BaseSystemModel
{
    protected $table = 'lookup';

    protected $fillable = ['app_id', 'role_id', 'user_id', 'name', 'value', 'private', 'description'];

    protected $casts = [
        'private' => 'boolean',
        'id'      => 'integer',
        'app_id'  => 'integer',
        'role_id' => 'integer',
        'user_id' => 'integer'
    ];

    protected $encrypted = ['value'];

    public static function boot()
    {
        parent::boot();

        /** @noinspection PhpUnusedParameterInspection */
        static::saved(
            function (BaseSystemLookup $lookup) {
                Session::setSessionLookups();
            }
        );

        /** @noinspection PhpUnusedParameterInspection */
        static::deleted(
            function (BaseSystemLookup $lookup) {
                Session::setSessionLookups();
            }
        );
    }

    /**
     * Removes unwanted fields from field list if supplied.
     *
     * @param mixed $fields
     *
     * @return array
     */
    public static function cleanFields($fields)
    {
        $fields = parent::cleanFields($fields);
        if (('*' !== array_get($fields, 0)) && !in_array('private', $fields)) {
            $fields[] = 'private';
        }

        return $fields;
    }


    /**
     * {@inheritdoc}
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (array_get_bool($attributes, 'private') && !is_null(array_get($attributes, 'value'))) {
            $attributes['value'] = $this->protectionMask;
        }

        return $attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // if mask, no need to do anything else.
        if (('value' === $key) && ($value === $this->protectionMask)) {
            return $this;
        }

        return parent::setAttribute($key, $value);
    }
}