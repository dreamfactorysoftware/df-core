<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Enums\DataFormats;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Arr;
use Illuminate\Support\MessageBag;

/**
 * Class DataFormatter
 *
 * @package DreamFactory\Core\Utility
 */
class DataFormatter
{
    /**
     * @param mixed  $data
     * @param string $sourceFormat
     * @param string $targetFormat
     *
     * @return array|mixed|null|string|boolean false if not successful
     */
    public static function reformatData($data, $sourceFormat = null, $targetFormat = null)
    {
        if (is_null($data) || ($sourceFormat == $targetFormat)) {
            return $data;
        }

        switch ($sourceFormat) {
            case DataFormats::JSON:
                if (is_array($data)) {
                    $data = self::jsonEncode($data);
                }
                switch ($targetFormat) {
                    case DataFormats::XML:
                        return static::jsonToXml($data);

                    case DataFormats::CSV:
                        return static::jsonToCsv($data);

                    case DataFormats::PHP_ARRAY:
                        return static::jsonToArray($data);
                }
                break;

            case DataFormats::XML:
                switch ($targetFormat) {
                    case DataFormats::JSON:
                        return static::xmlToJson($data);

                    case DataFormats::CSV:
                        return static::xmlToCsv($data);

                    case DataFormats::PHP_ARRAY:
                        return static::xmlToArray($data);
                }
                break;

            case DataFormats::CSV:
                switch ($targetFormat) {
                    case DataFormats::JSON:
                        return static::csvToJson($data);

                    case DataFormats::XML:
                        return static::csvToXml($data);

                    case DataFormats::PHP_ARRAY:
                        return static::csvToArray($data);
                }
                break;

            case DataFormats::PHP_ARRAY:
                switch ($targetFormat) {
                    case DataFormats::JSON:
                        //  Symfony Response object automatically converts this.
                        return $data;
//                        return static::arrayToJson( $data );

                    case DataFormats::XML:
                        return static::arrayToXml($data);

                    case DataFormats::CSV:
                        return static::arrayToCsv($data);

                    case DataFormats::TEXT:
                        return json_encode($data);

                    case DataFormats::PHP_ARRAY:
                        return $data;
                }
                break;

            case DataFormats::PHP_OBJECT:
                switch ($targetFormat) {
                    case DataFormats::JSON:
                        //  Symfony Response object automatically converts this.
                        return $data;
//                        return static::arrayToJson( $data );

                    case DataFormats::XML:
                        return static::arrayToXml($data);

                    case DataFormats::CSV:
                        return static::arrayToCsv($data);

                    case DataFormats::TEXT:
                        return json_encode($data);

                    case DataFormats::PHP_ARRAY:
                        return $data;
                }
                break;

            case DataFormats::RAW:
            case DataFormats::TEXT:
                // treat as string for the most part
                switch ($targetFormat) {
                    case DataFormats::JSON:
                        return json_encode($data);

                    case DataFormats::XML:
                        $root = config('df.xml_response_root', 'dfapi');

                        return '<?xml version="1.0" ?>' . "<$root>$data</$root>";

                    case DataFormats::CSV:
                    case DataFormats::TEXT:
                        return $data;

                    case DataFormats::PHP_ARRAY:
                        return [$data];
                }
                break;
        }

        return false;
    }

    // format helpers

    /**
     * xml2array() will convert the given XML text to an array in the XML structure.
     * Link: http://www.bin-co.com/php/scripts/xml2array/
     * Arguments : $contents - The XML text
     *             $get_attributes - 1 or 0. If this is 1 the function will
     *                               get the attributes as well as the tag values
     *                               - this results in a different array structure in the return value.
     *             $priority - Can be 'tag' or 'attribute'. This will change the way the resulting array structure.
     *                         For 'tag', the tags are given more importance.
     * Return: The parsed XML in an array form. Use print_r() to see the resulting array structure.
     * Examples: $array =  xml2array(file_get_contents('feed.xml'));
     *           $array =  xml2array(file_get_contents('feed.xml', 1, 'attribute'));
     */
    public static function xmlToArray($contents, $get_attributes = 0, $priority = 'tag')
    {
        if (empty($contents)) {
            return null;
        }

        if (!function_exists('xml_parser_create')) {
            //print "'xml_parser_create()' function not found!";
            return null;
        }

        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create('');
        xml_parser_set_option(
            $parser,
            XML_OPTION_TARGET_ENCODING,
            "UTF-8"
        ); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
        xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
        xml_parse_into_struct($parser, trim($contents), $xml_values);
        xml_parser_free($parser);

        if (!$xml_values) {
            return null;
        } //Hmm...

        //Initializations
        $xml_array = [];
        $current = &$xml_array; //Reference

        //Go through the tags.
        $repeated_tag_index = []; //Multiple tags with same name will be turned into an array
        foreach ($xml_values as $data) {
            unset($attributes, $value); //Remove existing values, or there will be trouble

            //This command will extract these variables into the foreach scope
            // tag(string) , type(string) , level(int) , attributes(array) .
            extract($data); //We could use the array by itself, but this cooler.

            $result = [];
            $attributes_data = [];

            if (isset($value)) {
                if ($priority == 'tag') {
                    $result = $value;
                } else {
                    $result['value'] = $value;
                } //Put the value in a assoc array if we are in the 'Attribute' mode
            }

            //Set the attributes too.
            if (isset($attributes) and $get_attributes) {
                foreach ($attributes as $attr => $val) {
                    if ($priority == 'tag') {
                        $attributes_data[$attr] = $val;
                    } else {
                        $result['attr'][$attr] = $val;
                    } //Set all the attributes in a array called 'attr'
                }
            }

            //See tag status and do the needed.
            /** @var string $type */
            /** @var string $tag */
            /** @var string $level */
            if ($type == "open") { //The starting of the tag '<tag>'
                $parent[$level - 1] = &$current;
                if (!is_array($current) or (!in_array($tag, array_keys($current)))) { //Insert New tag
                    $current[$tag] = $result;
                    if ($attributes_data) {
                        $current[$tag . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level] = 1;

                    $current = &$current[$tag];
                } else { //There was another element with the same tag name

                    if (isset($current[$tag][0])) { //If there is a 0th element it is already an array
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                        $repeated_tag_index[$tag . '_' . $level]++;
                    } else { //This section will make the value an array if multiple tags with the same name appear together
                        $current[$tag] = [
                            $current[$tag],
                            $result
                        ]; //This will combine the existing item and the new item together to make an array
                        $repeated_tag_index[$tag . '_' . $level] = 2;

                        if (isset($current[$tag .
                            '_attr'])) { //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset($current[$tag . '_attr']);
                        }
                    }
                    $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                    $current = &$current[$tag][$last_item_index];
                }
            } elseif ($type == "complete") { //Tags that ends in 1 line '<tag />'
                //See if the key is already taken.
                if (!isset($current[$tag])) { //New Key
                    $current[$tag] = (is_array($result) && empty($result)) ? '' : $result;
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ($priority == 'tag' and $attributes_data) {
                        $current[$tag . '_attr'] = $attributes_data;
                    }
                } else { //If taken, put all things inside a list(array)
                    if (isset($current[$tag][0]) and is_array($current[$tag])) { //If it is already an array...

                        // ...push the new element into that array.
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;

                        if ($priority == 'tag' and $get_attributes and $attributes_data) {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag . '_' . $level]++;
                    } else { //If it is not an array...
                        $current[$tag] = [
                            $current[$tag],
                            $result
                        ]; //...Make it an array using using the existing value and the new value
                        $repeated_tag_index[$tag . '_' . $level] = 1;
                        if ($priority == 'tag' and $get_attributes) {
                            if (isset($current[$tag .
                                '_attr'])) { //The attribute of the last(0th) tag must be moved as well

                                $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                                unset($current[$tag . '_attr']);
                            }

                            if ($attributes_data) {
                                $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                    }
                }
            } elseif ($type == 'close') { //End of tag '</tag>'
                $current = &$parent[$level - 1];
            }
        }

        return $xml_array;
    }

    /**
     * @param string $xml_string
     *
     * @return string
     * @throws \Exception
     */
    public static function xmlToJson($xml_string)
    {
        $xml = static::xmlToObject($xml_string);

        return static::arrayToJson((array)$xml);
    }

    /**
     * @param string $xml_string
     *
     * @return string
     * @throws \Exception
     */
    public static function xmlToCsv($xml_string)
    {
        $xml = static::xmlToObject($xml_string);

        return static::arrayToCsv((array)$xml);
    }

    /**
     * @param string $xml_string
     *
     * @return null|\SimpleXMLElement
     * @throws \Exception
     */
    public static function xmlToObject($xml_string)
    {
        if (empty($xml_string)) {
            return null;
        }

        libxml_use_internal_errors(true);
        try {
            if (false === $xml = simplexml_load_string($xml_string)) {
                throw new \Exception("Invalid XML Data: ");
            }
            // check for namespace
            $namespaces = $xml->getNamespaces();
            if (0 === $xml->count() && !empty($namespaces)) {
                $perNamespace = [];
                foreach ($namespaces as $prefix => $uri) {
                    if (false === $nsXml = simplexml_load_string($xml_string, null, 0, $prefix, true)) {
                        throw new \Exception("Invalid XML Namespace ($prefix) Data: ");
                    }
                    $perNamespace[$prefix] = $nsXml;
                }
                if (1 === count($perNamespace)) {
                    return current($perNamespace);
                }

                return $perNamespace;
            }

            return $xml;
        } catch (\Exception $ex) {
            $xmlstr = explode("\n", $xml_string);
            $errstr = "[INVALIDREQUEST]: Invalid XML Data: ";
            foreach (libxml_get_errors() as $error) {
                $errstr .= static::displayXmlError($error, $xmlstr) . "\n";
            }
            libxml_clear_errors();
            throw new \Exception($errstr);
        }
    }

    /**
     * @param string $json
     *
     * @return array
     * @throws \Exception
     */
    public static function jsonToArray($json)
    {
        if (empty($json)) {
            return null;
        }

        $array = json_decode($json, true);

        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $message = null;
                break;

            case JSON_ERROR_STATE_MISMATCH:
                $message = 'Invalid or malformed JSON';
                break;

            case JSON_ERROR_UTF8:
                $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;

            case JSON_ERROR_DEPTH:
                $message = 'The maximum stack depth has been exceeded';
                break;

            case JSON_ERROR_CTRL_CHAR:
                $message = 'Control character error, possibly incorrectly encoded';
                break;

            case JSON_ERROR_SYNTAX:
                $message = 'Syntax error, malformed JSON';
                break;

            default:
                $message = 'Unknown error';
                break;
        }

        if (!empty($message)) {
            throw new \InvalidArgumentException('JSON Error: ' . $message);
        }

        return $array;
    }

    /**
     * @param string $json
     *
     * @return string
     */
    public static function jsonToXml($json)
    {
        return static::arrayToXml(static::jsonToArray($json));
    }

    /**
     * @param string $json
     *
     * @return string
     */
    public static function jsonToCsv($json)
    {
        return static::arrayToCsv(static::jsonToArray($json));
    }

    /**
     * @param string $csv
     *
     * @return array
     */
    public static function csvToArray($csv)
    {
        // currently need to write out to file to use parser
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $filename = $tmpDir . 'csv_import' . time() . '.csv';
        file_put_contents($filename, $csv);

        // assume first row is field header
        $result = [];
        ini_set('auto_detect_line_endings', true);
        if (($handle = fopen($filename, "r")) !== false) {
            $headers = fgetcsv($handle, null, ",");
            while (false !== ($row = fgetcsv($handle))) {
                $new = [];
                foreach ($headers as $key => $value) {
                    $new[$value] = array_get($row, $key);
                }

                $result[] = $new;
            }

            fclose($handle);
            unlink($filename);
        }

        return $result;
    }

    /**
     * @param string $csv
     *
     * @return string
     */
    public static function csvToJson($csv)
    {
        return static::arrayToJson(static::csvToArray($csv));
    }

    /**
     * @param string $csv
     *
     * @return string
     */
    public static function csvToXml($csv)
    {
        return static::arrayToXml(static::csvToArray($csv));
    }

    /**
     * @param array $array
     *
     * @return string
     */
    public static function arrayToJson($array)
    {
        return static::jsonEncode($array);
    }

    /**
     * @param array $array
     * @param bool  $suppress_empty
     *
     * @return string
     */
    public static function simpleArrayToXml($array, $suppress_empty = false)
    {
        $xml = '';
        foreach ($array as $key => $value) {
            $value = trim($value, " ");
            if (empty($value) and (bool)$suppress_empty) {
                continue;
            }
            $htmlValue = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            if ($htmlValue != $value) {
                $xml .= "\t" . "<$key>$htmlValue</$key>\n";
            } else {
                $xml .= "\t" . "<$key>$value</$key>\n";
            }
        }

        return $xml;
    }

    /**
     * @param mixed  $data
     * @param string $root
     * @param int    $level
     * @param bool   $format
     *
     * @return string
     */
    protected static function arrayToXmlInternal($data, $root = null, $level = 1, $format = true)
    {
        $xml = null;
        if (is_array($data)) {
            if (!Arr::isAssoc($data)) {
                foreach ($data as $value) {
                    $xml .= self::arrayToXmlInternal($value, $root, $level, $format);
                }
            } else {
                if (Arr::isAssoc($data)) {
                    if (!empty($root)) {
                        if ($format) {
                            $xml .= str_repeat("\t", $level - 1);
                        }
                        $xml .= "<$root>";
                        if ($format) {
                            $xml .= "\n";
                        }
                    }
                    foreach ($data as $key => $value) {
                        $xml .= self::arrayToXmlInternal($value, $key, $level + 1, $format);
                    }
                    if (!empty($root)) {
                        if ($format) {
                            $xml .= str_repeat("\t", $level - 1);
                        }
                        $xml .= "</$root>";
                        if ($format) {
                            $xml .= "\n";
                        }
                    }
                } else {
                    // empty array
                    if (!empty($root)) {
                        if ($format) {
                            $xml .= str_repeat("\t", $level - 1);
                        }
                        $xml .= "<$root></$root>";
                        if ($format) {
                            $xml .= "\n";
                        }
                    }
                }
            }
        } elseif (is_object($data)) {
            if ($data instanceof Arrayable) {
                $xml .= self::arrayToXmlInternal($data->toArray(), $root, $level, $format);
            } else {
                $dataString = (string)$data;
                if (!empty($root)) {
                    if ($format) {
                        $xml .= str_repeat("\t", $level - 1);
                    }
                    $xml .= "<$root>$dataString</$root>";
                    if ($format) {
                        $xml .= "\n";
                    }
                }
            }
        } else {
            // not an array or object
            if (!empty($root)) {
                if ($format) {
                    $xml .= str_repeat("\t", $level - 1);
                }
                $xml .= "<$root>";
                if (!is_null($data)) {
                    if (is_bool($data)) {
                        $xml .= ($data) ? 'true' : 'false';
                    } else {
                        if (is_int($data) || is_float($data)) {
                            $xml .= $data;
                        } else {
                            if (is_string($data)) {
                                $htmlValue = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
                                $xml .= $htmlValue;
                            }
                        }
                    }
                }
                $xml .= "</$root>";
                if ($format) {
                    $xml .= "\n";
                }
            }
        }

        return $xml;
    }

    /**
     * @param mixed  $data
     * @param string $root
     * @param int    $level
     * @param bool   $format
     *
     * @return string
     */
    public static function arrayToXml($data, $root = null, $level = 1, $format = true)
    {
        if (empty($root)) {
            $root = config('df.xml_root', 'dfapi');
        }

        return '<?xml version="1.0" ?>' . static::arrayToXmlInternal($data, $root, $level, $format);
    }

    /**
     * @param array $array
     *
     * @return string
     */
    public static function arrayToCsv($array)
    {
        if (!is_array($array) || empty($array)) {
            return '';
        }

        $array = array_get($array, ResourcesWrapper::getWrapper(), array_get($array, 'error', $array));
        $data = [];

        if (!isset($array[0])) {
            $data[] = $array;
        } else {
            $data = $array;
        }

        $keys = array_keys(array_get($data, 0, []));

        // currently need to write out to file to use parser
        $tmpDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $filename = $tmpDir . 'csv_export' . time() . '.csv';

        $handle = fopen($filename, 'w');

        // build header row
        fputcsv($handle, $keys);

        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                // handle objects and array non-conformist to csv output
                if (is_array($value) || is_object($value)) {
                    $row[$key] = json_encode($value);
                }
            }

            fputcsv($handle, $row);
        }

        fclose($handle);
        $csv = file_get_contents($filename);
        unlink($filename);

        return $csv;
    }

    // other helpers

    /**
     * @param mixed $data Could be object, array, or simple type
     * @param bool  $prettyPrint
     *
     * @return null|string
     */
    public static function jsonEncode($data, $prettyPrint = false)
    {
        $data = static::export($data);

        if (version_compare(PHP_VERSION, '5.4', '>=')) {
            $options = JSON_UNESCAPED_SLASHES | (false !== $prettyPrint ? JSON_PRETTY_PRINT : 0) | JSON_NUMERIC_CHECK;

            return json_encode($data, $options);
        }

        $json = str_replace('\/', '/', json_encode($data));

        return $prettyPrint ? static::pretty_json($json) : $json;
    }

    /**
     * Build the array from data.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function export($data)
    {
        if (is_object($data)) {
            //	Allow embedded export method for specific export
            if ($data instanceof Arrayable || method_exists($data, 'toArray')) {
                $data = $data->toArray();
            } else {
                $data = get_object_vars($data);
            }
        }

        if (!is_array($data)) {
            return $data;
        }

        $output = [];

        foreach ($data as $key => $value) {
            $output[$key] = static::export($value);
        }

        return $output;
    }

    /**
     * Indents a flat JSON string to make it more human-readable.
     * Stolen from http://recursive-design.com/blog/2008/03/11/format-json-with-php/
     * and adapted to put spaces around : characters, then cleaned up.
     *
     * @param string $json The original JSON string to process.
     *
     * @return string Indented version of the original JSON string.
     */
    public static function pretty_json($json)
    {
        $result = null;
        $pos = 0;
        $length = strlen($json);
        $indentString = '  ';
        $newLine = PHP_EOL;
        $lastChar = null;
        $outOfQuotes = true;

        for ($i = 0; $i < $length; $i++) {
            //	Grab the next character in the string.
            $char = $json[$i];

            // Put spaces around colons
            if ($outOfQuotes && ':' == $char && ' ' != $lastChar) {
                $result .= ' ';
            }

            if ($outOfQuotes && ' ' != $char && ':' == $lastChar) {
                $result .= ' ';
            }

            // Are we inside a quoted string?
            if ('"' == $char && '\\' != $lastChar) {
                $outOfQuotes = !$outOfQuotes;
                // If this character is the end of an element,
                // output a new line and indent the next line.
            } else {
                if (($char == '}' || $char == ']') && $outOfQuotes) {
                    $result .= $newLine;
                    $pos--;
                    for ($j = 0; $j < $pos; $j++) {
                        $result .= $indentString;
                    }
                }
            }

            //	Add the character to the result string.
            $result .= $char;

            //	If the last character was the beginning of an element output a new line and indent the next line.
            if ((',' == $char || '{' == $char || '[' == $char) && $outOfQuotes) {
                $result .= $newLine;

                if ('{' == $char || '[' == $char) {
                    $pos++;
                }

                for ($j = 0; $j < $pos; $j++) {
                    $result .= $indentString;
                }
            }

            $lastChar = $char;
        }

        return $result;
    }

    /**
     * @param $error
     * @param $xml
     *
     * @return string
     */
    public static function displayXmlError($error, $xml)
    {
        $return = $xml[$error->line - 1] . "\n";
        $return .= str_repeat('-', $error->column) . "^\n";

        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $return .= "Warning $error->code: ";
                break;

            case LIBXML_ERR_ERROR:
                $return .= "Error $error->code: ";
                break;

            case LIBXML_ERR_FATAL:
                $return .= "Fatal Error $error->code: ";
                break;
        }

        $return .= trim($error->message) . "\n  Line: $error->line" . "\n  Column: $error->column";

        if ($error->file) {
            $return .= "\n  File: $error->file";
        }

        return "$return\n\n--------------------------------------------\n\n";
    }

    /**
     * Converts validation errors to plain string.
     *
     * @param $messages
     *
     * @return string
     */
    public static function validationErrorsToString($messages)
    {
        if ($messages instanceof MessageBag) {
            $messages = $messages->getMessages();
        }

        $errorString = '';

        if (is_array($messages)) {
            foreach ($messages as $field => $errors) {
                foreach ($errors as $error) {
                    $errorString .= ' ' . $error;
                }
            }
        }

        return $errorString;
    }

    /**
     * Checks to see if a string is printable or not.
     * Considers tab, carriage return, linefeed as printable.
     *
     * @param $string
     *
     * @return boolean
     */
    public static function isPrintable($string)
    {
        // Using regex here for more control. Could have used ctype_print but that
        // does not consider tab, carriage return, and linefeed as printable.
        return preg_match('/^[A-Za-zàèìòùÀÈÌÒÙáéíóúýÁÉÍÓÚÝâêîôûÂÊÎÔÛãñõÃÑÕäëïöüÿÄËÏÖÜŸçÇßØøÅåÆæœ0-9_~\-!@#\$%\^&\*\(\)\/\\\,=\"\'\.\s\[\]\(\)\{\}\+\-\?\<\>]+$/',
            $string);
    }

    public static function formatValue($value, $type)
    {
        $type = strtolower(strval($type));
        switch ($type) {
            case 'int':
            case 'integer':
                return intval($value);

            case 'decimal':
            case 'double':
            case 'float':
                return floatval($value);

            case 'boolean':
            case 'bool':
                return to_bool($value);

            case 'string':
                return strval($value);

            case 'time':
            case 'date':
            case 'datetime':
            case 'timestamp':
                $cfgFormat = static::getDateTimeFormat($type);

                return static::formatDateTime($cfgFormat, $value);

            case 'json':
                return json_decode($value, true);
        }

        return $value;
    }

    public static function getDateTimeFormat($type)
    {
        switch (strtolower(strval($type))) {
            case 'time':
                return \Config::get('df.db.time_format');

            case 'date':
                return \Config::get('df.db.date_format');

            case 'datetime':
                return \Config::get('df.db.datetime_format');

            case 'timestamp':
                return \Config::get('df.db.timestamp_format');
        }

        return null;
    }

    public static function formatDateTime($out_format, $in_value = null, $in_format = null)
    {
        //  If value is null, current date and time are returned
        if (!empty($out_format)) {
            $in_value = (is_string($in_value) || is_null($in_value)) ? $in_value : strval($in_value);
            if (!empty($in_format)) {
                if (false === $date = \DateTime::createFromFormat($in_format, $in_value)) {
                    \Log::error("Failed to format datetime from '$in_value'' to '$in_format'");

                    return $in_value;
                }
            } else {
                $date = new \DateTime($in_value);
            }

            return $date->format($out_format);
        }

        return $in_value;
    }
}