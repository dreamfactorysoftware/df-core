<?php

namespace DreamFactory\Core\Models;

use DreamFactory\Core\Components\Encryptable;
use DreamFactory\Core\Components\Protectable;
use DreamFactory\Core\Components\Validatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Database\Eloquent\Concerns\GuardsAttributes;
use Illuminate\Database\Eloquent\Concerns\HasAttributes;
use Illuminate\Database\Eloquent\Concerns\HidesAttributes;
use Illuminate\Database\Eloquent\JsonEncodingException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use JsonSerializable;

/**
 * Class BaseServiceConfigHandler
 *
 * @package DreamFactory\Core\Components
 */
class NoDbModel implements Arrayable, Jsonable, JsonSerializable
{
    use HasAttributes {
        getAttributeValue as public getAttributeValueBase;
        getAttributeFromArray as protected getAttributeFromArrayBase;
        setAttribute as public setAttributeBase;
        attributesToArray as public attributesToArrayBase;
    }

    use HidesAttributes, GuardsAttributes, Protectable, Encryptable, Validatable;

    protected static $schema = [];

    /**
     * Create a new NoDB model instance.
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->setRawAttributes((array) $attributes, true);
    }

    // Overriding some behaviors here
    public function getFillable()
    {
        return collect(static::getSchema())->pluck('name')->all();
    }

    public function getCasts()
    {
        $casts = [];
        foreach (static::getSchema() as $field) {
            switch ($type = array_get($field, 'type')) {
                case 'object': // really want array here
                    $casts[array_get($field, 'name')] = 'array';
                    break;
                case 'array':
                case 'boolean':
                case 'integer':
                case 'string':
                case 'real':
                case 'float':
                case 'double':
                case 'date':
                case 'datetime':
                case 'timestamp':
                    $casts[array_get($field, 'name')] = $type;
                    break;
            }
        }

        return $casts;
    }

    // The HasAttributes trait calls $this->getDates() and $this->getDateFormat() internally
    // during attributesToArray(). The trait's own getDates() implementation depends on
    // usesTimestamps() / $dates, neither of which NoDbModel inherits (it doesn't extend
    // Model and doesn't use HasTimestamps). We provide stub overrides that short-circuit
    // the date handling so attributesToArray() works for this Model-less use case.
    //
    // Historical note: Eloquent's Model::getDates() was removed in Laravel 10, but the
    // method still lives in HasAttributes and is invoked from inside the trait, so the
    // override remains necessary as long as NoDbModel uses HasAttributes directly.
    public function getDates()
    {
        return property_exists($this, 'dates') ? (array) $this->dates : [];
    }

    protected function getDateFormat()
    {
        return property_exists($this, 'dateFormat') && $this->dateFormat
            ? $this->dateFormat
            : 'Y-m-d H:i:s';
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array $attributes
     * @return $this
     *
     * @throws \Illuminate\Database\Eloquent\MassAssignmentException
     */
    public function fill(array $attributes)
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            // The developers may choose to place some attributes in the "fillable" array
            // which means only those attributes may be set through mass assignment to
            // the model, and all others will just get ignored for security reasons.
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new MassAssignmentException($key);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSchema()
    {
        return static::$schema;
    }

    public function toArray()
    {
        return $this->attributesToArray();
    }

    public function toJson($options = 0)
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributeValue($key)
    {
        $value = $this->getAttributeValueBase($key);
        $this->protectAttribute($key, $value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    protected function getAttributeFromArray($key)
    {
        $value = $this->getAttributeFromArrayBase($key);
        $this->decryptAttribute($key, $value);

        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttribute($key, $value)
    {
        // if protected, and trying to set the mask, throw it away
        if ($this->isProtectedAttribute($key, $value)) {
            return $this;
        }

        $return = $this->setAttributeBase($key, $value);

        $value = $this->attributes[$key];
        $this->encryptAttribute($key, $value);
        $this->attributes[$key] = $value;

        return $return;
    }

    /**
     * {@inheritdoc}
     */
    public function attributesToArray()
    {
        $attributes = $this->attributesToArrayBase();

        $attributes = $this->addDecryptedAttributesToArray($attributes);

        $attributes = $this->addProtectedAttributesToArray($attributes);

        return $attributes;
    }
}