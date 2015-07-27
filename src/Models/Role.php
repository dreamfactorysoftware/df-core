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
    ];

    protected $casts = ['is_active' => 'boolean', 'id' => 'integer'];

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (Role $role){
                if (!$role->is_active) {
                    JWTUtilities::invalidateTokenByRoleId($role->id);
                }
                CacheUtilities::forgetRoleInfo($role->id);
            }
        );

        static::deleting(
            function (Role $role){
                JWTUtilities::invalidateTokenByRoleId($role->id);
                CacheUtilities::forgetRoleInfo($role->id);
            }
        );
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