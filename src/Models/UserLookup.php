<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class UserLookup extends BaseSystemLookup
{
    protected $fillable = ['user_id', 'name', 'value', 'private', 'description'];
    protected $hidden = ['app_id', 'role_id'];

    protected static function findCompositeForeignKeyModel($foreign, $data)
    {
        if (empty($userId = array_get($data, 'user_id'))) {
            $userId = $foreign;
        }

        if (!empty($name = array_get($data, 'name'))) {
            return static::whereUserId($userId)->whereName($name)->first();
        }

        return null;
    }

    public function fill(array $attributes)
    {
        if (array_key_exists('name', $attributes)) {
            $newName = array_get($attributes, 'name');
            $userId = array_get($attributes, 'user_id', $this->user_id);
            if (!empty($userId) && (0!==strcasecmp($this->name, $newName))) {
                // check if lookup by that name already exists
                if (static::whereUserId($userId)->whereName($newName)->exists()) {
                    throw new BadRequestException('Lookup name can not be modified to one that already exists.');
                }
            }
        }

        return parent::fill($attributes);
    }
}