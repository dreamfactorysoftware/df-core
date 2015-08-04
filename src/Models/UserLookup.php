<?php

namespace DreamFactory\Core\Models;

/**
 * UserLookup
 *
 * @property integer $id
 * @property integer $user_id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereName($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereIsPrivate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereLastModifiedDate($value)
 */
class UserLookup extends BaseSystemLookup
{
    protected $table = 'user_lookup';

    protected $fillable = ['user_id', 'name', 'value', 'private', 'description'];

    protected $casts = ['id' => 'integer', 'user_id' => 'integer', 'is_private' => 'boolean'];
}