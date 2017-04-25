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
     * @return boolean
     */
    function array_get_bool($array, $key, $default = false)
    {
        return to_bool(array_get($array, $key, $default));
    }
}

if (!function_exists('neutralize')) {
    /**
     * Given a string, return it to neutral format (lowercase, period and underscores)
     *
     * @param string $item  The string to neutralize
     * @param string $strip If provided, it's value is removed from item before it's neutralized.
     *                      Example: "REQUEST_URI" would be "URI" with $strip = "REQUEST_"
     *
     * @return string
     */
    function neutralize($item, $strip = null)
    {
        if (is_numeric($item)) {
            return $item;
        }

        if (null !== $strip) {
            $item = str_ireplace($strip, null, $item);
        }

        //	Split by forward slash, backslash, period, or space...
        $_parts = preg_split("/[. \/\\\\]+/", $item);

        if (!empty($_parts)) {
            foreach ($_parts as $_index => $_part) {
                $_parts[$_index] = decamelize($_part);
            }
        }

        return implode('.', $_parts);
    }
}

if (!function_exists('neutralizeObject')) {
    /**
     * Given an object, returns an array containing the variables of the object and their values.
     * The keys for the object have been neutralized for your protection
     *
     * @param object $object
     * @param string $strip If provided, it's value is removed from item before it's neutralized.
     *                      Example: "REQUEST_URI" would be "URI" with $strip = "REQUEST_"
     *
     * @return string
     */
    function neutralizeObject($object, $strip = null)
    {
        $_variables = is_array($object) ? $object : get_object_vars($object);

        if (!empty($_variables)) {
            foreach ($_variables as $_key => $_value) {
                $_originalKey = $_key;

                if ($strip) {
                    $_key = str_replace($strip, null, $_key);
                }

                $_variables[neutralize(ltrim($_key, '_'))] = $_value;
                unset($_variables[$_originalKey]);
            }
        }

        return $_variables;
    }
}

if (!function_exists('display')) {
    /**
     * Given a neutralized string, return it to suitable for framing
     *
     * @param string $item The string to frame
     *
     * @return string
     */
    function display($item)
    {
        return camelize(str_replace(['_', '.', '\\', '/'],
            ' ',
            $item),
            '_',
            true,
            false);
    }
}

if (!function_exists('deneutralize')) {
    /**
     * Given a string, return it to non-neutral format (delimited camel-case)
     *
     * @param string $item      The string to deneutralize
     * @param bool   $isKey     True if the string is an array/object key/tag
     * @param string $delimiter Will be used to reconstruct the string
     *
     * @return string
     */
    function deneutralize($item, $isKey = false, $delimiter = '\\')
    {
        if (is_numeric($item)) {
            return $item;
        }

        return camelize(str_replace(['_', '.', $delimiter],
            ' ',
            $item),
            '_',
            false,
            $isKey);
    }
}

if (!function_exists('baseName')) {
    /**
     * @param string $tag
     * @param string $delimiter
     *
     * @return string
     */
    function baseName($tag, $delimiter = '\\')
    {
        return @end(@explode($delimiter, $tag));
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
        empty($separator) && $separator = ['_', '-'];

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

if (!function_exists('isPlural')) {
    /**
     * This function is NOT smart. It only looks for an 's' at the end of a word. You have been warned.
     *
     * @param string $word
     * @param bool   $returnSingular If true, the word without the "s" is returned.
     *
     * @return bool|string
     */
    function isPlural($word, $returnSingular = false)
    {
        if (empty($word) || !is_string($word) || strlen($word) < 3) {
            return false;
        }

        $_temp = $word[strlen($word) - 1];

        if ('s' == $_temp && $word == pluralize(substr($word, 0, -1))) {
            if (false !== $returnSingular) {
                return substr($word, 0, -1);
            }

            return true;
        }

        return false;
    }
}

if (!function_exists('pluralize')) {
    /**
     * Converts a word to its plural form. Totally swiped from Yii
     *
     * @param string $name the word to be pluralized
     *
     * @return string the pluralized word
     */
    function pluralize($name)
    {
        /** @noinspection SpellCheckingInspection */
        static $_blacklist = [
            'Amoyese',
            'bison',
            'Borghese',
            'bream',
            'breeches',
            'britches',
            'buffalo',
            'cantus',
            'carp',
            'chassis',
            'clippers',
            'cod',
            'coitus',
            'Congoese',
            'contretemps',
            'corps',
            'debris',
            'deer',
            'diabetes',
            'djinn',
            'eland',
            'elk',
            'equipment',
            'Faroese',
            'flounder',
            'Foochowese',
            'gallows',
            'Genevese',
            'geese',
            'Genoese',
            'Gilbertese',
            'graffiti',
            'headquarters',
            'herpes',
            'hijinks',
            'Hottentotese',
            'information',
            'innings',
            'jackanapes',
            'Kiplingese',
            'Kongoese',
            'Lucchese',
            'mackerel',
            'Maltese',
            '.*?media',
            'metadata',
            'mews',
            'moose',
            'mumps',
            'Nankingese',
            'news',
            'nexus',
            'Niasese',
            'Pekingese',
            'Piedmontese',
            'pincers',
            'Pistoiese',
            'pliers',
            'Portuguese',
            'proceedings',
            'rabies',
            'rice',
            'rhinoceros',
            'salmon',
            'Sarawakese',
            'scissors',
            'sea[- ]bass',
            'series',
            'Shavese',
            'shears',
            'siemens',
            'species',
            'swine',
            'testes',
            'trousers',
            'trout',
            'tuna',
            'Vermontese',
            'Wenchowese',
            'whiting',
            'wildebeest',
            'Yengeese',
        ];
        /** @noinspection SpellCheckingInspection */
        static $_rules = [
            '/(s)tatus$/i'                                                                 => '\1\2tatuses',
            '/(quiz)$/i'                                                                   => '\1zes',
            '/^(ox)$/i'                                                                    => '\1en',
            '/(matr|vert|ind)(ix|ex)$/i'                                                   => '\1ices',
            '/([m|l])ouse$/i'                                                              => '\1ice',
            '/(x|ch|ss|sh|us|as|is|os)$/i'                                                 => '\1es',
            '/(shea|lea|loa|thie)f$/i'                                                     => '\1ves',
            '/(buffal|tomat|potat|ech|her|vet)o$/i'                                        => '\1oes',
            '/([^aeiouy]|qu)ies$/i'                                                        => '\1y',
            '/([^aeiouy]|qu)y$/i'                                                          => '\1ies',
            '/(?:([^f])fe|([lre])f)$/i'                                                    => '\1\2ves',
            '/([ti])um$/i'                                                                 => '\1a',
            '/sis$/i'                                                                      => 'ses',
            '/move$/i'                                                                     => 'moves',
            '/foot$/i'                                                                     => 'feet',
            '/human$/i'                                                                    => 'humans',
            '/tooth$/i'                                                                    => 'teeth',
            '/(bu)s$/i'                                                                    => '\1ses',
            '/(hive)$/i'                                                                   => '\1s',
            '/(p)erson$/i'                                                                 => '\1eople',
            '/(m)an$/i'                                                                    => '\1en',
            '/(c)hild$/i'                                                                  => '\1hildren',
            '/(alumn|bacill|cact|foc|fung|nucle|octop|radi|stimul|syllab|termin|vir)us$/i' => '\1i',
            '/us$/i'                                                                       => 'uses',
            '/(alias)$/i'                                                                  => '\1es',
            '/(ax|cris|test)is$/i'                                                         => '\1es',
            '/s$/'                                                                         => 's',
            '/$/'                                                                          => 's',
        ];

        if (empty($name) || in_array(strtolower($name), $_blacklist)) {
            return $name;
        }

        foreach ($_rules as $_rule => $_replacement) {
            if (preg_match($_rule, $name)) {
                return preg_replace($_rule, $_replacement, $name);
            }
        }

        return $name;
    }
}
