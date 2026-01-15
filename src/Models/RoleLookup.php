<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class RoleLookup extends BaseSystemLookup
{
    protected $fillable = ['role_id', 'name', 'value', 'private', 'description'];
    protected $hidden = ['app_id', 'user_id'];

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
            $roleId = array_get($attributes, 'role_id', $this->role_id);
            if (!empty($roleId) && (0!==strcasecmp($this->name, $newName))) {
                // check if lookup by that name already exists
                if (static::whereRoleId($roleId)->whereName($newName)->exists()) {
                    throw new BadRequestException('Lookup name can not be modified to one that already exists.');
                }
            }
        }

        return parent::fill($attributes);
    }
}