<?php
namespace DreamFactory\Core\Models;

/**
 * UserAppRole
 *
 * @property integer $user_id
 * @property integer $app_id
 * @property integer $role_id
 * @method static \Illuminate\Database\Query\Builder|UserAppRole whereUserId($value)
 * @method static \Illuminate\Database\Query\Builder|UserAppRole whereAppId($value)
 * @method static \Illuminate\Database\Query\Builder|UserAppRole whereRoleId($value)
 */
class UserAppRole extends BaseModel
{
    protected $table = 'user_to_app_to_role';

    protected $fillable = ['user_id', 'app_id', 'role_id'];

    protected $casts = ['user_id' => 'integer', 'app_id' => 'integer', 'role_id' => 'integer'];

    public $timestamps = false;
}