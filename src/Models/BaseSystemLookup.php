<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Library\Utility\Scalar;
use Illuminate\Database\Query\Builder;

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
 * @method static Builder|Lookup whereId($value)
 * @method static Builder|Lookup whereName($value)
 * @method static Builder|Lookup whereValue($value)
 * @method static Builder|Lookup whereDescription($value)
 * @method static Builder|Lookup whereIsPrivate($value)
 * @method static Builder|Lookup whereCreatedDate($value)
 * @method static Builder|Lookup whereLastModifiedDate($value)
 */
class BaseSystemLookup extends BaseSystemModel
{
    protected $fillable = ['name', 'value', 'private', 'description'];

    protected $casts = ['private' => 'boolean', 'id' => 'integer'];

    protected $encrypted = ['value'];

    /**
     * {@inheritdoc}
     */
    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (Scalar::boolval(array_get($attributes, 'private')) && !is_null(array_get($attributes, 'value'))) {
            $attributes['value'] = static::PROTECTION_MASK;
        }

        return $attributes;
    }

    // Don't use mutators here as it disrupts the flow of encryption
    /**
     * {@inheritdoc}
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);
        // if protected, no need to do anything else, mask it.
        if (('private' === $key) && Scalar::boolval(array_get($this->attributes, 'private')) && !is_null($value)) {
            return static::PROTECTION_MASK;
        }

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // if mask, no need to do anything else.
        if (('private' === $key) && ($value === static::PROTECTION_MASK)) {
            return $this;
        }

        return parent::setAttribute($key, $value);
    }
}