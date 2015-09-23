<?php
namespace DreamFactory\Core\Models;

use \Cache;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Library\Utility\ArrayUtils;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

    protected $rules = [
        'name' => 'required'
    ];

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
                \Cache::forget('role:' . $role->id);
            }
        );

        static::deleted(
            function (Role $role){
                JWTUtilities::invalidateTokenByRoleId($role->id);
                \Cache::forget('role:' . $role->id);
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

    /**
     * Returns role info cached, or reads from db if not present.
     * Pass in a key to return a portion/index of the cached data.
     *
     * @param int         $id
     * @param null|string $key
     * @param null        $default
     *
     * @return mixed|null
     */
    public static function getCachedInfo($id, $key = null, $default = null)
    {
        $cacheKey = 'role:' . $id;
        try {
            $result = \Cache::remember($cacheKey, \Config::get('df.default_cache_ttl'), function () use ($id){
                $role = Role::with(
                    [
                        'role_lookup_by_role_id',
                        'role_service_access_by_role_id',
                        'service_by_role_service_access'
                    ]
                )->whereId($id)->first();

                if (empty($role)) {
                    throw new NotFoundException("Role not found.");
                }

                if (!$role->is_active) {
                    throw new ForbiddenException("Role is not active.");
                }

                $roleInfo = $role->toArray();
                $services = ArrayUtils::get($roleInfo, 'service_by_role_service_access');
                unset($roleInfo['service_by_role_service_access']);

                foreach ($roleInfo['role_service_access_by_role_id'] as $key => $value) {
                    $serviceName = ArrayUtils::findByKeyValue(
                        $services,
                        'id',
                        ArrayUtils::get($value, 'service_id'), 'name'
                    );
                    $component = ArrayUtils::get($value, 'component');
                    $roleInfo['role_service_access_by_role_id'][$key]['service'] = $serviceName;
                    $roleInfo['role_service_access_by_role_id'][$key]['component'] = trim($component, '/');
                }

                return $roleInfo;
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