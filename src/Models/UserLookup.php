<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use Illuminate\Database\Query\Builder;

/**
 * UserLookup
 *
 * @property integer $user_id
 * @method static Builder|RoleLookup whereUserId($value)
 */
class UserLookup extends BaseSystemLookup
{
    protected $table = 'user_lookup';

    protected $fillable = ['user_id', 'name', 'value', 'private', 'description'];

    protected $casts = ['id' => 'integer', 'user_id' => 'integer', 'private' => 'boolean'];

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
            if (0!==strcasecmp($this->name, $newName)) {
                // check if lookup by that name already exists
                if (static::whereUserId($this->user_id)->whereName($newName)->exists()) {
                    throw new BadRequestException('Lookup name can not be modified to one that already exists.');
                }
            }
        }

        return parent::fill($attributes);
    }
}