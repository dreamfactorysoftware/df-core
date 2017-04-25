<?php namespace DreamFactory\Core\Utility;

/**
 * Scalar utility class
 */
class Scalar
{
    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param mixed $value
     *
     * @return bool
     */
    public static function boolval($value)
    {
        if (\is_bool($value)) {
            return $value;
        }

        $_value = \strtolower((string)$value);

        //	FILTER_VALIDATE_BOOLEAN doesn't catch 'Y' or 'N', so convert to full words...
        if ('y' == $_value) {
            $_value = 'yes';
        } elseif ('n' == $_value) {
            $_value = 'no';
        }

        return \filter_var($_value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Multi-argument is_array helper
     *
     * Usage: is_array( $array1[, $array2][, ...])
     *
     * @param mixed      $possibleArray
     * @param mixed|null $_ [optional]
     *
     * @return bool
     */
    public static function is_array($possibleArray, $_ = null)
    {
        foreach (func_get_args() as $_argument) {
            if (!is_array($_argument)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convenience "in_array" method. Takes variable args.
     *
     * The first argument is the needle, the rest are considered in the haystack. For example:
     *
     * Option::in( 'x', 'x', 'y', 'z' ) returns true
     * Option::in( 'a', 'x', 'y', 'z' ) returns false
     *
     * @param mixed $needle
     * @param mixed $haystack
     *
     * @return bool
     */
    public static function in_array($needle, $haystack)
    {
        return static::in(func_get_args());
    }

    /**
     * Prepend an array
     *
     * @param array  $array
     * @param string $string
     * @param bool   $deep
     *
     * @return array
     */
    public static function array_prepend($array, $string, $deep = false)
    {
        if (empty($array) || empty($string)) {
            return $array;
        }

        foreach ($array as $key => $element) {
            if (is_array($element)) {
                if ($deep) {
                    $array[$key] = self::array_prepend($element, $string, $deep);
                }
            } else {
                $array[$key] = $string . $element;
            }
        }

        return $array;
    }

    /**
     * Takes a list of things and returns them in an array as the values. Keys are maintained.
     *
     * @param ...
     *
     * @return array
     */
    public static function argsToArray()
    {
        $_array = [];

        foreach (func_get_args() as $_key => $_argument) {
            $_array[$_key] = $_argument;
        }

        //	Return the fresh array...
        return $_array;
    }

    /**
     * Returns the first non-empty argument or null if none found.
     * Allows for multiple nvl chains. Example:
     *
     *<code>
     *    if ( null !== Option::nvl( $x, $y, $z ) ) {
     *        //    none are null
     *    } else {
     *        //    One of them is null
     *    }
     *
     * IMPORTANT NOTE!
     * Since PHP evaluates the arguments before calling a function, this is NOT a short-circuit method.
     *
     * @return mixed
     */
    public static function nvl()
    {
        $_default = null;
        $_args = func_num_args();
        $_haystack = func_get_args();

        for ($_i = 0; $_i < $_args; $_i++) {
            if (null !== ($_default = IfSet::get($_haystack, $_i))) {
                break;
            }
        }

        return $_default;
    }

    /**
     * Convenience "in_array" method. Takes variable args.
     *
     * The first argument is the needle, the rest are considered in the haystack. For example:
     *
     * Option::in( 'x', 'x', 'y', 'z' ) returns true
     * Option::in( 'a', 'x', 'y', 'z' ) returns false
     *
     * @return bool
     */
    public static function in()
    {
        $_haystack = func_get_args();

        if (!empty($_haystack) && count($_haystack) > 1) {
            $_needle = array_shift($_haystack);

            return in_array($_needle, $_haystack);
        }

        return false;
    }

    /**
     * Concatenates $parts into a single string delimited by $delimiter
     *
     * @param array|string $parts     The string or array of strings to concatenate
     * @param bool|false   $leading   If true, a leading $delimiter will be pre-pended
     * @param string       $delimiter The concatenation delimiter. Defaults to a dot (".")
     *
     * @return null|string
     */
    public static function concat($parts = [], $leading = false, $delimiter = '.')
    {
        $_parts = [];

        if (!empty($parts)) {
            foreach (is_array($parts) ? $parts : [$parts] as $_portion) {
                //  Remove $delimiter from front and back
                $_portion = trim($_portion, $delimiter);

                if (!empty($_portion) && $delimiter != $_portion) {
                    $_parts[] = $_portion;
                }
            }
        }

        //  Ensure leading $delimiter if wanted
        $_result = ($leading ? $delimiter : null) . trim(implode($delimiter, $_parts), $delimiter);

        //  Return the string or null if empty
        return (empty($_result) || $_result == $delimiter) ? null : $_result;
    }
}