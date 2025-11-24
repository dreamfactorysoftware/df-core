<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\Session;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
    public function validate($data, $throwException = true)
    {
        if (parent::validate($data)) {
            $userId = array_get($data, 'user_id');
            $appId = array_get($data, 'app_id');

            if ($userId && $appId) {
                $model = $this->whereAppId($appId)->whereUserId($userId)->first();

                if (!empty($model) && $model->id != array_get($data, 'id')) {
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
            function (UserAppRole $map) {
                static::setRoleIdByAppIdAndUserId($map->app_id, $map->user_id, $map->role_id);
            }
        );

        static::deleted(
            function (UserAppRole $map) {
                static::setRoleIdByAppIdAndUserId($map->app_id, $map->user_id, null);
            }
        );
    }

    /**
     * Use this primarily in middle-ware or where no session is established yet.
     * Once session is established, the role id is accessible via Session.
     *
     * @param int $app_id
     * @param int $user_id
     *
     * @return null|int The role id or null for admin
     */
    public static function getRoleIdByAppIdAndUserId($app_id, $user_id)
    {
        $cacheKey = static::makeRoleIdCacheKey($app_id, $user_id);
        $result = \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'),
            function () use ($app_id, $user_id) {
                try {
                    $roleId = static::whereAppId($app_id)
                        ->whereUserId($user_id)
                        ->value('role_id');
                    // try to get at least some role if it wasn't found by an app id
                    if (empty($roleId)) {
                        $roleId = static::whereUserId($user_id)->value('role_id');
                    }
                    return $roleId ?? null;
                } catch (ModelNotFoundException $ex) {
                    return null;
                }
            });

        return $result;
    }

    /**
     * @param $app_id
     * @param $user_id
     * @param $role_id
     */
    public static function setRoleIdByAppIdAndUserId($app_id, $user_id, $role_id)
    {
        $cacheKey = static::makeRoleIdCacheKey($app_id, $user_id);
        \Cache::put($cacheKey, $role_id, \Config::get('df.default_cache_ttl'));
    }

    /**
     * @param $app_id
     * @param $user_id
     * @return string
     */
    public static function makeRoleIdCacheKey($app_id, $user_id)
    {
        return "app-$app_id:user-$user_id:role_id";
    }
}