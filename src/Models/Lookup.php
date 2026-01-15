<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;

class Lookup extends BaseSystemLookup
{
    protected $fillable = ['name', 'value', 'private', 'description'];
    protected $hidden = ['app_id', 'role_id', 'user_id'];

    protected static function findCompositeForeignKeyModel($foreign, $data)
    {
        if (!empty($name = array_get($data, 'name'))) {
            return static::whereAppId(null)->whereRoleId(null)->whereUserId(null)->whereName($name)->first();
        }

        return null;
    }

    public function fill(array $attributes)
    {
        if (array_key_exists('name', $attributes)) {
            $newName = array_get($attributes, 'name');
            if (0!==strcasecmp($this->name, $newName)) {
                // check if lookup by that name already exists
                if (static::whereAppId(null)->whereRoleId(null)->whereUserId(null)->whereName($newName)->exists()) {
                    throw new BadRequestException('Lookup name can not be modified to one that already exists.');
                }
            }
        }

        return parent::fill($attributes);
    }

    public static function selectByRequest(array $criteria = [], array $options = [])
    {
        $reducer = '(app_id IS NULL) AND (role_id IS NULL) AND (user_id IS NULL)';
        $criteria['condition'] = (empty($condition = array_get($criteria, 'condition')) ? $reducer :
            "($condition) AND $reducer");
        return parent::selectByRequest($criteria, $options);
    }
}