<?php
namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Utility\JWTUtilities;

/**
 * RoleServiceAccess
 *
 * @property integer $id
 * @property integer $role_id
 * @property integer $service_id
 * @property string  $component
 * @property integer $verb_mask
 * @property integer $requestor_mask
 * @property array   $filters
 * @property string  $filter_op
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|RoleServiceAccess whereId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleServiceAccess whereRoleId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleServiceAccess whereServiceId($value)
 * @method static \Illuminate\Database\Query\Builder|RoleServiceAccess whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|RoleServiceAccess whereLastModifiedDate($value)
 */
class RoleServiceAccess extends BaseSystemModel
{
    protected $table = 'role_service_access';

    protected $guarded = ['id'];

    protected $casts = [
        'filters'        => 'array',
        'verb_mask'      => 'integer',
        'requestor_mask' => 'integer',
        'id'             => 'integer',
        'role_id'        => 'integer',
        'service_id'     => 'integer'
    ];

    public static function boot()
    {
        parent::boot();

        static::saved(
            function (RoleServiceAccess $rsa){
                \Cache::forget('role:'.$rsa->role_id);
            }
        );

        static::deleted(
            function (RoleServiceAccess $rsa){
                \Cache::forget('role:'.$rsa->role_id);
            }
        );
    }

    /**
     * Save a new model and return the instance in case it hasn't been created yet.
     *
     * @param array $attributes
     *
     * @return bool|BaseModel
     * @throws \Exception
     */
    public static function createUnique(array $attributes = [])
    {
        $roleId = array_get($attributes, "role_id");
        $serviceId = array_get($attributes, "service_id");
        $component = array_get($attributes, "component");
        $requestorMask = array_get($attributes, "requestor_mask");

        $doExist = RoleServiceAccess::whereRoleId($roleId)->whereServiceId($serviceId)->whereComponent($component)->whereRequestorMask($requestorMask)->exists();
        if (!$doExist) {
            return RoleServiceAccess::create($attributes);
        } else {
            return false;
        }
    }

}