<?php
namespace DreamFactory\Core\Utility;

class ArrayUtils
{
    /**
     * @param array|\ArrayObject $target            Target to grab $key from
     * @param string             $key               Index into target to retrieve
     * @param mixed              $defaultValue      Value returned if $key is not in $target
     * @param bool               $emptyStringIsNull If true, and the result is an empty string (''), NULL is returned
     *
     * @return mixed
     */
    public static function get(array $target, $key, $defaultValue = null, $emptyStringIsNull = false)
    {
        $_result =
            is_array($target) ? (array_key_exists($key, $target) ? $target[$key] : $defaultValue) : $defaultValue;

        return $emptyStringIsNull && '' === $_result ? null : $_result;
    }

    /**
     * @param array|\ArrayObject $options
     * @param string             $key
     * @param string             $subKey
     * @param mixed              $defaultValue      Only applies to target value
     * @param bool               $emptyStringIsNull If true, empty() values will always return as NULL
     *
     * @return mixed
     */
    public static function getDeep(array $options, $key, $subKey, $defaultValue = null, $emptyStringIsNull = false)
    {
        $_deep = static::get($options, $key, [], $emptyStringIsNull);

        return static::get($_deep, $subKey, $defaultValue, $emptyStringIsNull);
    }

    /**
     * Retrieves a boolean option from the given array. $defaultValue is set and returned if $_key is not 'set'.
     *
     * Returns TRUE for "1", "true", "on", "yes" and "y". Returns FALSE otherwise.
     *
     * @param array|\ArrayAccess $options
     * @param string             $key
     * @param boolean            $defaultValue Defaults to false
     *
     * @return mixed
     */
    public static function getBool(array $options, $key, $defaultValue = false)
    {
        return Scalar::boolval(static::get($options, $key, $defaultValue));
    }

    /**
     * @param array|\ArrayObject $target Target to set $key in
     * @param string             $key    Index into target to retrieve
     * @param mixed              $value  The value to set
     */
    public static function set(array & $target, $key, $value = null)
    {
        is_array($target) && $target[$key] = $value;
    }

    /**
     * @param array|\ArrayObject $target Target to set $key in
     * @param string             $key    Index into target to retrieve
     *
     * @return bool True if key existed and was removed
     */
    public static function remove(array & $target, $key)
    {
        if (static::has($target, $key)) {
            unset($target[$key]);

            return true;
        }

        return false;
    }

    /**
     * Removes items with null value from an array.
     *
     * @param array $array
     */
    public static function removeNull(array & $array)
    {
        foreach ($array as $key => $value) {
            if (null === $value) {
                unset($array[$key]);
            }
        }
    }

    /**
     * @param array|\ArrayObject $target Target to check
     * @param string             $key    Key to check
     *
     * @return bool
     */
    public static function has(array $target, $key)
    {
        return is_array($target) && array_key_exists($key, $target);
    }

    /**
     * A recursive array_change_key_case lowercase function.
     *
     * @param array $input
     *
     * @return array
     */
    public static function array_key_lower($input)
    {
        if (!is_array($input)) {
            trigger_error("Invalid input array '{$input}'", E_USER_NOTICE);
            exit;
        }
        $input = array_change_key_case($input, CASE_LOWER);
        foreach ($input as $key => $array) {
            if (is_array($array)) {
                $input[$key] = static::array_key_lower($array);
            }
        }

        return $input;
    }

    /**
     * @param $array
     *
     * @return bool
     */
    public static function isArrayNumeric($array)
    {
        if (is_array($array)) {
            for ($k = 0, reset($array); $k === key($array); next($array)) {
                ++$k;
            }

            return is_null(key($array));
        }

        return false;
    }

    /**
     * @param      $array
     * @param bool $strict
     *
     * @return bool
     */
    public static function isArrayAssociative($array, $strict = true)
    {
        if (is_array($array)) {
            if (!empty($array)) {
                if ($strict) {
                    return (count(array_filter(array_keys($array), 'is_string')) == count($array));
                } else {
                    return (0 !== count(array_filter(array_keys($array), 'is_string')));
                }
            }
        }

        return false;
    }

    /**
     * @param        $list
     * @param        $find
     * @param string $delimiter
     * @param bool   $strict
     *
     * @return bool
     */
    public static function isInList($list, $find, $delimiter = ',', $strict = false)
    {
        return (false !== array_search($find, array_map('trim', explode($delimiter, strtolower($list))), $strict));
    }

    /**
     * @param        $list
     * @param        $find
     * @param string $delimiter
     * @param bool   $strict
     *
     * @return mixed
     */
    public static function findInList($list, $find, $delimiter = ',', $strict = false)
    {
        return array_search($find, array_map('trim', explode($delimiter, strtolower($list))), $strict);
    }

    /**
     * @param        $list
     * @param        $find
     * @param string $delimiter
     * @param bool   $strict
     *
     * @return string
     */
    public static function addOnceToList($list, $find, $delimiter = ',', $strict = false)
    {
        if (empty($list)) {
            $list = $find;

            return $list;
        }
        $pos = array_search($find, array_map('trim', explode($delimiter, strtolower($list))), $strict);
        if (false !== $pos) {
            return $list;
        }
        $result = array_map('trim', explode($delimiter, $list));
        $result[] = $find;

        return implode($delimiter, array_values($result));
    }

    /**
     * @param        $list
     * @param        $find
     * @param string $delimiter
     * @param bool   $strict
     *
     * @return string
     */
    public static function removeOneFromList($list, $find, $delimiter = ',', $strict = false)
    {
        $pos = array_search($find, array_map('trim', explode($delimiter, strtolower($list))), $strict);
        if (false === $pos) {
            return $list;
        }
        $result = array_map('trim', explode($delimiter, $list));
        unset($result[$pos]);

        return implode($delimiter, array_values($result));
    }

    /**+
     * Provides a diff of two arrays, recursively.
     * Any keys or values that do not match are returned in an array.
     * Empty results indicate no change obviously.
     *
     * @param array $array1
     * @param array $array2
     * @param bool  $check_both_directions
     *
     * @return array
     */
    public static function array_diff_recursive(array $array1, $array2, $check_both_directions = false)
    {
        $_return = [];

        if ($array1 !== $array2) {
            foreach ($array1 as $_key => $_value) {
                //	Is the key is there...
                if (!array_key_exists($_key, $array2)) {
                    $_return[$_key] = $_value;
                    continue;
                }

                //	Not an array?
                if (!is_array($_value)) {
                    if ($_value !== $array2[$_key]) {
                        $_return[$_key] = $_value;
                        continue;
                    }
                }

                //	If we've got two arrays, diff 'em
                if (is_array($array2[$_key])) {
                    $_diff = static::array_diff_recursive($_value, $array2[$_key]);

                    if (!empty($_diff)) {
                        $_return[$_key] = $_diff;
                    }

                    continue;
                }

                $_return[$_key] = $_value;
            }

            if ($check_both_directions) {
                foreach ($array2 as $_key => $_value) {
                    //	Is the key is there...
                    if (!array_key_exists($_key, $array1)) {
                        $_return[$_key] = $_value;
                        continue;
                    }

                    //	Not an array?
                    if (!is_array($_value)) {
                        if ($_value !== $array1[$_key]) {
                            $_return[$_key] = $_value;
                            continue;
                        }
                    }

                    //	If we've got two arrays, diff 'em
                    if (is_array($array1[$_key])) {
                        $_diff = static::array_diff_recursive($_value, $array1[$_key]);

                        if (!empty($_diff)) {
                            $_return[$_key] = $_diff;
                        }

                        continue;
                    }

                    $_return[$_key] = $_value;
                }
            }
        }

        return $_return;
    }

    /**
     * A case-insensitive "in_array" for all intents and purposes. Works with objects too!
     *
     * @param string       $needle
     * @param array|object $haystack
     * @param bool         $strict
     *
     * @return bool Returns true if found, false otherwise. Just like in_array
     */
    public static function contains($needle, $haystack, $strict = false)
    {
        foreach ($haystack as $_index => $_value) {
            if (is_string($_value)) {
                if (0 === strcasecmp($needle, $_value)) {
                    return true;
                }
            } else if (in_array($needle, $_value, $strict)) {
                return true;
            }
        }

        return false;
    }

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
    public static function findByKeyValue($array, $key, $value, $returnKey = null)
    {
        foreach ($array as $item) {
            if ($item[$key] === $value) {
                if ($returnKey) {
                    return $item[$returnKey];
                } else {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * Ensures the argument passed in is actually an array with optional iteration callback
     *
     * @static
     *
     * @param array             $array
     * @param callable|\Closure $callback
     *
     * @return array
     */
    public static function clean($array = null, $callback = null)
    {
        $_result = (empty($array) ? [] : (!is_array($array) ? [$array] : $array));

        if (null === $callback || !is_callable($callback)) {
            return $_result;
        }

        $_response = [];

        foreach ($_result as $_item) {
            $_response[] = call_user_func($callback, $_item);
        }

        return $_response;
    }
}
