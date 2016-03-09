<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;

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

    protected $casts = [
        'id'      => 'integer',
        'user_id' => 'integer',
        'app_id'  => 'integer',
        'role_id' => 'integer'
    ];

    public $timestamps = false;

    /** @inheritdoc */
    public function validate(array $data = [], $throwException = true)
    {
        if (empty($data)) {
            $data = $this->attributes;
        }

        if (parent::validate($data)) {
            $userId = ArrayUtils::get($data, 'user_id');
            $appId = ArrayUtils::get($data, 'app_id');

            if ($userId && $appId) {
                $model = $this->whereAppId($appId)->whereUserId($userId)->first();

                if (!empty($model) && $model->id !== ArrayUtils::get($data, 'id')) {
                    throw new BadRequestException('Multiple user-to-app-to-role assignment. You can only have a single user-to-app-to-role assignment.');
                }
            }

            return true;
        }

        return false;
    }

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (UserAppRole $map){
                Session::setRoleIdByAppIdAndUserId($map->app_id, $map->user_id, $map->role_id);
            }
        );

        static::deleted(
            function (UserAppRole $map){
                Session::setRoleIdByAppIdAndUserId($map->app_id, $map->user_id, null);
            }
        );
    }
}