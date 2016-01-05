<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Library\Utility\ArrayUtils;

/**
 * BaseSystemLookup - an abstract base class for system lookups
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $description
 * @property boolean $is_private
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Lookup whereId($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereName($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereValue($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereDescription($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereIsPrivate($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereCreatedDate($value)
 * @method static \Illuminate\Database\Query\Builder|Lookup whereLastModifiedDate($value)
 */
class BaseSystemLookup extends BaseSystemModel
{
    const PRIVATE_MASK = '**********';

    protected $fillable = ['name', 'value', 'private', 'description'];

    protected $casts = ['private' => 'boolean', 'id' => 'integer'];

    protected $encrypted = ['value'];

    /**
     * Convert the model instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        $attributes = $this->attributesToArray();

        if (ArrayUtils::getBool($attributes, 'private')) {
            $attributes['value'] = self::PRIVATE_MASK;
        }

        return array_merge($attributes, $this->relationsToArray());
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'value' && $value === self::PRIVATE_MASK && $this->exists) {
            $value = $this->value;
        }

        parent::setAttribute($key, $value);
    }
}