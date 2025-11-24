<?php

namespace DreamFactory\Core\Components;

/**
 * Class Protectable
 *
 * @package DreamFactory\Core\Components
 */
trait Protectable
{
    /**
     * Set this to false to use internally to expose passwords, etc., i.e. unprotected.
     *
     * @var boolean
     */
    public $protectedView = true;

    /**
     * Mask to return when visible, but masked, attributes are returned from toArray()
     *
     * @var string
     */
    protected $protectionMask = '**********';

    /**
     * The attributes that should be visible, but masked in arrays, if used externally.
     *
     * @var array
     */
    protected $protected = [];

    /**
     * Get list of protectable attributes
     *
     * @return array
     */
    public function getProtectable()
    {
        return $this->protected;
    }

    /**
     * Set list of protectable attributes
     *
     * @param array $names
     * @return $this
     */
    public function protectable(array $names)
    {
        $this->protected = $names;

        return $this;
    }

    /**
     * Check if the attribute coming from client is set to mask. If so, skip writing to database.
     *
     * @param string $key   Attribute name
     * @param mixed  $value Value of the attribute $key
     * @return bool
     */
    protected function isProtectedAttribute($key, $value)
    {
        return (in_array($key, $this->getProtectable()) && ($value === $this->protectionMask));
    }

    /**
     * Check if the attribute is marked protected, if so return the mask, not the value.
     *
     * @param string $key   Attribute name
     * @param mixed  $value Value of the attribute $key, updated to mask if protected
     * @return bool Whether or not the attribute is being protected
     */
    protected function protectAttribute($key, &$value)
    {
        if (!is_null($value) && $this->protectedView && in_array($key, $this->getProtectable())) {
            $value = $this->protectionMask;

            return true;
        }

        return false;
    }

    /**
     * Replace all protected attributes in the given array with the mask
     *
     * @param array $attributes
     * @return array
     */
    protected function addProtectedAttributesToArray(array $attributes)
    {
        if ($this->protectedView) {
            foreach ($this->getProtectable() as $key) {
                if (isset($attributes[$key])) {
                    $attributes[$key] = $this->protectionMask;
                }
            }
        }

        return $attributes;
    }
}