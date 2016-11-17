<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Exceptions\BadRequestException;
use Illuminate\Database\Query\Builder;

/**
 * AppLookup
 *
 * @property integer $app_id
 * @method static Builder|RoleLookup whereAppId($value)
 */
class AppLookup extends BaseSystemLookup
{
    protected $table = 'app_lookup';

    protected $fillable = ['app_id', 'name', 'value', 'private', 'description'];

    protected $casts = ['is_private' => 'boolean', 'id' => 'integer', 'app_id' => 'integer'];

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
            if (0!==strcasecmp($this->name, $newName)) {
                // check if lookup by that name already exists
                if (static::whereAppId($this->app_id)->whereName($newName)->exists()) {
                    throw new BadRequestException('Lookup name can not be modified to one that already exists.');
                }
            }
        }

        return parent::fill($attributes);
    }
}