<?php

namespace DreamFactory\Core\Models;

/**
 * AppLookup
 *
 * @property integer $id
 * @property integer $app_id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereAppId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereName($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereIsPrivate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereLastModifiedDate($value)
 */
class AppLookup extends BaseSystemLookup
{
    protected $table = 'app_lookup';

    protected $fillable = ['app_id', 'name', 'value', 'private', 'description'];

    protected $casts = [
        'is_private' => 'boolean',
        'id'         => 'integer',
        'app_id'     => 'integer',
    ];
}