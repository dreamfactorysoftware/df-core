<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use Illuminate\Database\Query\Builder;

/**
 * RoleLookup
 *
 * @property integer $role_id
 * @method static Builder|RoleLookup whereRoleId($value)
 */
class RoleLookup extends BaseSystemLookup
{
    protected $table = 'role_lookup';

    protected $fillable = ['role_id', 'name', 'value', 'private', 'description'];

    protected $casts = ['private' => 'boolean', 'id' => 'integer', 'role_id' => 'integer'];

    protected static function findCompositeForeignKeyModel($foreign, $data)
    {
        if (empty($roleId = array_get($data, 'role_id'))) {
            $roleId = $foreign;
        }

        if (!empty($name = array_get($data, 'name'))) {
            return static::whereRoleId($roleId)->whereName($name)->first();
        }

        return null;
    }

    public function fill(array $attributes)
    {
        if (array_key_exists('name', $attributes)) {
            $newName = array_get($attributes, 'name');
            if (0!==strcasecmp($this->name, $newName)) {
                // check if lookup by that name already exists
                if (static::whereRoleId($this->role_id)->whereName($newName)->exists()) {
                    throw new BadRequestException('Lookup name can not be modified to one that already exists.');
                }
            }
        }

        return parent::fill($attributes);
    }
}