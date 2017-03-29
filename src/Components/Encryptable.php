<?php

namespace DreamFactory\Core\Components;

use Crypt;

/**
 * Class Encryptable
 *
 * @package DreamFactory\Core\Components
 */
trait Encryptable
{
    /**
     * Set this to true to use externally to keep values encrypted.
     *
     * @var boolean
     */
    public $encryptedView = false;

    /**
     * The attributes that should be encrypted on write, decrypted on read.
     *
     * @var array
     */
    protected $encrypted = [];

    /**
     * Get list of encryptable attributes
     *
     * @return array
     */
    public function getEncryptable()
    {
        return $this->encrypted;
    }

    /**
     * Set list of encryptable attributes
     *
     * @param array $names
     * @return $this
     */
    public function encryptable(array $names)
    {
        $this->encrypted = $names;

        return $this;
    }

    /**
     * Check if the attribute coming from client is set to mask. If so, skip writing to database.
     *
     * @param string $key Attribute name
     * @return bool
     */
    protected function isEncryptedAttribute($key)
    {
        return (in_array($key, $this->getEncryptable()));
    }

    /**
     * Check if the attribute is marked encrypted, if so return the decrypted value.
     *
     * @param string $key   Attribute name
     * @param mixed  $value Value of the attribute $key, decrypt if encrypted
     * @return bool Whether or not the attribute is being decrypted
     */
    protected function encryptAttribute($key, &$value)
    {
        if (!is_null($value) && in_array($key, $this->getEncryptable())) {
            $value = Crypt::encrypt($value);

            return true;
        }

        return false;
    }

    /**
     * Check if the attribute is marked encrypted, if so return the decrypted value.
     *
     * @param string $key   Attribute name
     * @param mixed  $value Value of the attribute $key, decrypt if encrypted
     * @return bool Whether or not the attribute is being decrypted
     */
    protected function decryptAttribute($key, &$value)
    {
        if (!is_null($value) && !$this->encryptedView && in_array($key, $this->getEncryptable())) {
            $value = Crypt::decrypt($value);

            return true;
        }

        return false;
    }

    /**
     * Decrypt encryptable attributes found in outgoing array
     *
     * @param array $attributes
     * @return array
     */
    protected function addDecryptedAttributesToArray(array $attributes)
    {
        if (!$this->encryptedView) {
            foreach ($this->getEncryptable() as $key) {
                if (!array_key_exists($key, $attributes)) {
                    continue;
                }

                if (!empty($attributes[$key])) {
                    $attributes[$key] = Crypt::decrypt($attributes[$key]);
                }
            }
        }

        return $attributes;
    }
}