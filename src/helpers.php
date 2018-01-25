<?php

if (!function_exists('to_bool')) {
    /**
     * Convert any value to boolean value.
     *
     * @param mixed $value
     *
     * @return bool
     */
    function to_bool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $_value = strtolower((string)$value);

        //	FILTER_VALIDATE_BOOLEAN doesn't catch 'Y' or 'N', so convert to full words...
        if ('y' == $_value) {
            $_value = 'yes';
        } elseif ('n' == $_value) {
            $_value = 'no';
            //	FILTER_VALIDATE_BOOLEAN doesn't catch 'T' or 'F', so convert to full words...
        } elseif ('t' == $_value) {
            $_value = 'true';
        } elseif ('f' == $_value) {
            $_value = 'false';
        }

        return filter_var($_value, FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('array_get_bool')) {
    /**
     * Get an item from an array using "dot" notation, convert to a boolean response.
     *
     * @param  \ArrayAccess|array $array
     * @param  string             $key
     * @param  boolean            $default
     *
     * @return boolean
     */
    function array_get_bool($array, $key, $default = false)
    {
        return to_bool(array_get($array, $key, $default));
    }
}

if (!function_exists('array_by_key_value')) {
    /**
     * Searches a multi-dimension array by key and value and returns
     * the array that holds the key => value pair or optionally returns
     * the value of a supplied key from the resultant array.
     *
     * @param array  $array
     * @param string $key
     * @param string $value
     * @param string $returnKey
     *
     * @return null
     */
    function array_by_key_value($array, $key, $value, $returnKey = null)
    {
        if (is_array($array)) {
            foreach ($array as $item) {
                if ($value === array_get($item, $key)) {
                    if ($returnKey) {
                        return array_get($item, $returnKey);
                    } else {
                        return $item;
                    }
                }
            }
        }

        return null;
    }
}

if (!function_exists('camelize')) {
    /**
     * Converts a separator delimited string to camel case
     *
     * @param string  $string
     * @param string  $separator
     * @param boolean $preserveWhiteSpace
     * @param bool    $isKey If true, first word is lower-cased
     *
     * @return string
     */
    function camelize($string, $separator = null, $preserveWhiteSpace = false, $isKey = false)
    {
        if (empty($separator)) {
            $separator = ['_', '-'];
        }

        $_newString = ucwords(str_replace($separator, ' ', $string));

        if (false !== $isKey) {
            $_newString = lcfirst($_newString);
        }

        return (false === $preserveWhiteSpace ? str_replace(' ', null, $_newString) : $_newString);
    }
}

if (!function_exists('decamelize')) {
    /**
     * Converts a camel-cased word to a delimited lowercase string
     *
     * @param string $string
     *
     * @return string
     */
    function decamelize($string)
    {
        return strtolower(preg_replace("/([a-z])([A-Z])/", "\\1_\\2", $string));
    }
}

if (!function_exists('array_get_or')) {
    /**
     * Retrieves array key-value based on multiple keys before returning the default.
     *
     * Example: You want to retrieve either 'user' or 'user_id' key from array $data.
     *          $value = array_get_or($data, ['user', 'user_id']);
     *
     * This is equivalent to :
     *          $value = array_get($data, 'user', array_get($data, 'user_id'));
     *
     * @param array $data
     * @param array $keys
     * @param null  $default
     *
     * @return mixed|null
     */
    function array_get_or(array $data, array $keys, $default = null)
    {
        $out = null;
        foreach ($keys as $key) {
            $out = array_get($data, $key);
            if ($out !== null) {
                break;
            }
        }

        return ($out === null) ? $default : $out;
    }
}

if (!function_exists('str_to_utf8')){
    /**
     * Converts non utf-8 encoded strings to utf-8 encoded
     *
     * @param $str
     *
     * @return string
     */
    function str_to_utf8($str)
    {
        if (mb_detect_encoding($str, 'UTF-8', true) === false) {
            $str = utf8_encode($str);
        }

        return $str;
    }
}