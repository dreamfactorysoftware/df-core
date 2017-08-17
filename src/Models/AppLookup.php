<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class AppLookup extends BaseSystemLookup
{
    protected $fillable = ['app_id', 'name', 'value', 'private', 'description'];
    protected $hidden = ['role_id', 'user_id'];

    protected static function findCompositeForeignKeyModel($foreign, $data)
    {
        if (empty($appId = array_get($data, 'app_id'))) {
            $appId = $foreign;
        }

        if (!empty($name = array_get($data, 'name'))) {
            return static::whereAppId($appId)->whereName($name)->first();
        }

        return null;
    }

    public function fill(array $attributes)
    {
        if (array_key_exists('name', $attributes)) {
            $newName = array_get($attributes, 'name');
            $appId = array_get($attributes, 'app_id', $this->app_id);
            if (!empty($appId) && (0!==strcasecmp($this->name, $newName))) {
                // check if lookup by that name already exists
                if (static::whereAppId($appId)->whereName($newName)->exists()) {
                    throw new BadRequestException('Lookup name can not be modified to one that already exists.');
                }
            }
        }

        return parent::fill($attributes);
    }
}