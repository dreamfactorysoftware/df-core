<?php

namespace DreamFactory\Core\Models;

/**
 * RoleLookup
 *
 * @property integer $id
 * @property integer $role_id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereRoleId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereName($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereIsPrivate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleLookup whereLastModifiedDate($value)
 */
class RoleLookup extends BaseSystemLookup
{
    protected $table = 'role_lookup';

    protected $fillable = ['role_id', 'name', 'value', 'private', 'description'];

    protected $casts = ['private' => 'boolean', 'id' => 'integer', 'role_id' => 'integer'];
}