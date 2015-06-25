<?php
namespace DreamFactory\Core\Models;

use \Cache;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Utility\CacheUtilities;

/**
 * Role
 *
 * @property integer $id
 * @property string  $name
 * @property string  $description
 * @property boolean $is_active
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Role whereId($value)
 * @method static \Illuminate\Database\Query\Builder|Role whereName($value)
 * @method static \Illuminate\Database\Query\Builder|Role whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|Role whereIsActive($value)
 * @method static \Illuminate\Database\Query\Builder|Role whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|Role whereLastModifiedDate($value)
 */
class Role extends BaseSystemModel
{
    protected $table = 'role';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'role_service_access_by_role_id',
        'role_lookup_by_role_id'
    ];

    protected $hidden = [
        'user_to_app_to_role_by_role_id',
        'app_by_user_to_app_to_role',
        'user_by_user_to_app_to_role',
        'user_by_role_lookup'
    ];

    protected $casts = ['is_active' => 'boolean'];

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (Role $role){
                if(!$role->is_active) {
                    JWTUtilities::invalidateTokenByRoleId($role->id);
                }
                Role::clearCache($role);
            }
        );

        static::deleting(
            function (Role $role){
                JWTUtilities::invalidateTokenByRoleId($role->id);
                Role::clearCache($role);
            }
        );
    }

    public static function clearCache(Role $role)
    {
        $role->load('app_by_role_id', 'app_by_user_to_app_to_role', 'user_to_app_to_role_by_role_id');

        $apps = $role->getRelation('app_by_role_id')->toArray();

        foreach ($apps as $app) {
            $apiKey = ArrayUtils::get($app, 'api_key');

            $cacheKey = CacheUtilities::makeApiKeyUserIdKey($apiKey);

            if (Cache::has($cacheKey)) {
                Cache::forget($cacheKey);
            }
        }

        $appUsers = $role->getRelation('app_by_user_to_app_to_role')->toArray();

        $userRoles = $role->getRelation('user_to_app_to_role_by_role_id')->toArray();

        foreach ($appUsers as $au) {
            $apiKey = ArrayUtils::get($au, 'api_key');
            $roleId = ArrayUtils::getDeep($au, 'pivot', 'role_id');
            $appId = ArrayUtils::getDeep($au, 'pivot', 'app_id');

            foreach ($userRoles as $ur) {
                if ($appId === ArrayUtils::get($ur, 'app_id') && $roleId === ArrayUtils::get($ur, 'role_id')) {
                    $userId = ArrayUtils::get($ur, 'user_id');
                    $cacheKey = CacheUtilities::makeApiKeyUserIdKey($apiKey, $userId);

                    if (Cache::has($cacheKey)) {
                        if ($appId === ArrayUtils::get($ur, 'app_id') && $roleId === ArrayUtils::get($ur, 'role_id')) {
                            $userId = ArrayUtils::get($ur, 'user_id');
                            $cacheKey = CacheUtilities::makeApiKeyUserIdKey($apiKey, $userId);

                            if (Cache::has($cacheKey)) {
                                Cache::forget($cacheKey);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getRoleServiceAccess()
    {
        $this->load('role_service_access_by_role_id', 'service_by_role_service_access');
        $rsa = $this->getRelation('role_service_access_by_role_id')->toArray();
        $services = $this->getRelation('service_by_role_service_access')->toArray();

        foreach ($rsa as $key => $s) {
            $serviceName = ArrayUtils::findByKeyValue($services, 'id', ArrayUtils::get($s, 'service_id'), 'name');
            ArrayUtils::set($rsa[$key], 'service', $serviceName);
        }

        return $rsa;
    }
}