<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Utility;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Enums\DataFormats;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Class DataFormatter
 *
 * @package DreamFactory\Rave\Utility
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
    public static function reformatData( $data, $sourceFormat = null, $targetFormat = null )
    {
        if ( is_null( $data ) || ( $sourceFormat == $targetFormat ) )
        {
            return $data;
        }

        switch ( $sourceFormat )
        {
            case DataFormats::JSON:
                switch ( $targetFormat )
                {
                    case DataFormats::XML:
                        return static::jsonToXml( $data );

                    case DataFormats::CSV:
                        return static::jsonToCsv( $data );

                    case DataFormats::PHP_ARRAY:
                        return static::jsonToArray( $data );
                }
                break;

            case DataFormats::XML:
                switch ( $targetFormat )
                {
                    case DataFormats::JSON:
                        return static::xmlToJson( $data );

                    case DataFormats::CSV:
                        return static::xmlToCsv( $data );

                    case DataFormats::PHP_ARRAY:
                        return static::xmlToArray( $data );
                }
                break;

            case DataFormats::CSV:
                switch ( $targetFormat )
                {
                    case DataFormats::JSON:
                        return static::csvToJson( $data );

                    case DataFormats::XML:
                        return static::csvToXml( $data );

                    case DataFormats::PHP_ARRAY:
                        return static::csvToArray( $data );
                }
                break;

            case DataFormats::PHP_ARRAY:
                switch ( $targetFormat )
                {
                    case DataFormats::JSON:
                        //  Symfony Response object automatically converts this.
                        return $data;
//                        return static::arrayToJson( $data );

                    case DataFormats::XML:
                        return static::arrayToXml( $data );

                    case DataFormats::CSV:
                        return static::arrayToCsv( $data );

                    case DataFormats::TEXT:
                        return json_encode( [ 'response' => $data ] );

                    case DataFormats::PHP_ARRAY:
                        return $data;
                }
                break;

            case DataFormats::PHP_OBJECT:
                switch ( $targetFormat )
                {
                    case DataFormats::JSON:
                        //  Symfony Response object automatically converts this.
                        return $data;
//                        return static::arrayToJson( $data );

                    case DataFormats::XML:
                        return static::arrayToXml( $data );

                    case DataFormats::CSV:
                        return static::arrayToCsv( $data );

                    case DataFormats::TEXT:
                        return json_encode( [ 'response' => $data ] );

                    case DataFormats::PHP_ARRAY:
                        return $data;
                }

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
    public static function xmlToArray( $contents, $get_attributes = 0, $priority = 'tag' )
    {
        if ( empty( $contents ) )
        {
            return null;
        }

        if ( !function_exists( 'xml_parser_create' ) )
        {
            //print "'xml_parser_create()' function not found!";
            return null;
        }

        //Get the XML parser of PHP - PHP must have this module for the parser to work
        $parser = xml_parser_create( '' );
        xml_parser_set_option(
            $parser,
            XML_OPTION_TARGET_ENCODING,
            "UTF-8"
        ); # http://minutillo.com/steve/weblog/2004/6/17/php-xml-and-character-encodings-a-tale-of-sadness-rage-and-data-loss
        xml_parser_set_option( $parser, XML_OPTION_CASE_FOLDING, 0 );
        xml_parser_set_option( $parser, XML_OPTION_SKIP_WHITE, 1 );
        xml_parse_into_struct( $parser, trim( $contents ), $xml_values );
        xml_parser_free( $parser );

        if ( !$xml_values )
        {
            return null;
        } //Hmm...

        //Initializations
        $xml_array = [ ];
        $current = &$xml_array; //Reference

        //Go through the tags.
        $repeated_tag_index = [ ]; //Multiple tags with same name will be turned into an array
        foreach ( $xml_values as $data )
        {
            unset( $attributes, $value ); //Remove existing values, or there will be trouble

            //This command will extract these variables into the foreach scope
            // tag(string) , type(string) , level(int) , attributes(array) .
            extract( $data ); //We could use the array by itself, but this cooler.

            $result = [ ];
            $attributes_data = [ ];

            if ( isset( $value ) )
            {
                if ( $priority == 'tag' )
                {
                    $result = $value;
                }
                else
                {
                    $result['value'] = $value;
                } //Put the value in a assoc array if we are in the 'Attribute' mode
            }

            //Set the attributes too.
            if ( isset( $attributes ) and $get_attributes )
            {
                foreach ( $attributes as $attr => $val )
                {
                    if ( $priority == 'tag' )
                    {
                        $attributes_data[$attr] = $val;
                    }
                    else
                    {
                        $result['attr'][$attr] = $val;
                    } //Set all the attributes in a array called 'attr'
                }
            }

            //See tag status and do the needed.
            /** @var string $type */
            /** @var string $tag */
            /** @var string $level */
            if ( $type == "open" )
            { //The starting of the tag '<tag>'
                $parent[$level - 1] = &$current;
                if ( !is_array( $current ) or ( !in_array( $tag, array_keys( $current ) ) ) )
                { //Insert New tag
                    $current[$tag] = $result;
                    if ( $attributes_data )
                    {
                        $current[$tag . '_attr'] = $attributes_data;
                    }
                    $repeated_tag_index[$tag . '_' . $level] = 1;

                    $current = &$current[$tag];
                }
                else
                { //There was another element with the same tag name

                    if ( isset( $current[$tag][0] ) )
                    { //If there is a 0th element it is already an array
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;
                        $repeated_tag_index[$tag . '_' . $level]++;
                    }
                    else
                    { //This section will make the value an array if multiple tags with the same name appear together
                        $current[$tag] = [
                            $current[$tag],
                            $result
                        ]; //This will combine the existing item and the new item together to make an array
                        $repeated_tag_index[$tag . '_' . $level] = 2;

                        if ( isset( $current[$tag . '_attr'] ) )
                        { //The attribute of the last(0th) tag must be moved as well
                            $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                            unset( $current[$tag . '_attr'] );
                        }
                    }
                    $last_item_index = $repeated_tag_index[$tag . '_' . $level] - 1;
                    $current = &$current[$tag][$last_item_index];
                }
            }
            elseif ( $type == "complete" )
            { //Tags that ends in 1 line '<tag />'
                //See if the key is already taken.
                if ( !isset( $current[$tag] ) )
                { //New Key
                    $current[$tag] = ( is_array( $result ) && empty( $result ) ) ? '' : $result;
                    $repeated_tag_index[$tag . '_' . $level] = 1;
                    if ( $priority == 'tag' and $attributes_data )
                    {
                        $current[$tag . '_attr'] = $attributes_data;
                    }
                }
                else
                { //If taken, put all things inside a list(array)
                    if ( isset( $current[$tag][0] ) and is_array( $current[$tag] ) )
                    { //If it is already an array...

                        // ...push the new element into that array.
                        $current[$tag][$repeated_tag_index[$tag . '_' . $level]] = $result;

                        if ( $priority == 'tag' and $get_attributes and $attributes_data )
                        {
                            $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                        }
                        $repeated_tag_index[$tag . '_' . $level]++;
                    }
                    else
                    { //If it is not an array...
                        $current[$tag] = [
                            $current[$tag],
                            $result
                        ]; //...Make it an array using using the existing value and the new value
                        $repeated_tag_index[$tag . '_' . $level] = 1;
                        if ( $priority == 'tag' and $get_attributes )
                        {
                            if ( isset( $current[$tag . '_attr'] ) )
                            { //The attribute of the last(0th) tag must be moved as well

                                $current[$tag]['0_attr'] = $current[$tag . '_attr'];
                                unset( $current[$tag . '_attr'] );
                            }

                            if ( $attributes_data )
                            {
                                $current[$tag][$repeated_tag_index[$tag . '_' . $level] . '_attr'] = $attributes_data;
                            }
                        }
                        $repeated_tag_index[$tag . '_' . $level]++; //0 and 1 index is already taken
                    }
                }
            }
            elseif ( $type == 'close' )
            { //End of tag '</tag>'
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
    public static function xmlToJson( $xml_string )
    {
        $_xml = static::xmlToObject( $xml_string );

        return static::arrayToJson( (array)$_xml );
    }

    /**
     * @param string $xml_string
     *
     * @return string
     * @throws \Exception
     */
    public static function xmlToCsv( $xml_string )
    {
        $_xml = static::xmlToObject( $xml_string );

        return static::arrayToCsv( (array)$_xml );
    }

    /**
     * @param string $xml_string
     *
     * @return null|\SimpleXMLElement
     * @throws \Exception
     */
    public static function xmlToObject( $xml_string )
    {
        if ( empty( $xml_string ) )
        {
            return null;
        }

        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_string );
        if ( !$xml )
        {
            $xmlstr = explode( "\n", $xml_string );
            $errstr = "[INVALIDREQUEST]: Invalid XML Data: ";
            foreach ( libxml_get_errors() as $error )
            {
                $errstr .= static::displayXmlError( $error, $xmlstr ) . "\n";
            }
            libxml_clear_errors();
            throw new \Exception( $errstr );
        }

        return $xml;
    }

    /**
     * @param string $json
     *
     * @return array
     * @throws \Exception
     */
    public static function jsonToArray( $json )
    {
        if ( empty( $json ) )
        {
            return null;
        }

        $_array = json_decode( $json, true );

        switch ( json_last_error() )
        {
            case JSON_ERROR_NONE:
                $_message = null;
                break;

            case JSON_ERROR_STATE_MISMATCH:
                $_message = 'Invalid or malformed JSON';
                break;

            case JSON_ERROR_UTF8:
                $_message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;

            case JSON_ERROR_DEPTH:
                $_message = 'The maximum stack depth has been exceeded';
                break;

            case JSON_ERROR_CTRL_CHAR:
                $_message = 'Control character error, possibly incorrectly encoded';
                break;

            case JSON_ERROR_SYNTAX:
                $_message = 'Syntax error, malformed JSON';
                break;

            default:
                $_message = 'Unknown error';
                break;
        }

        if ( !empty( $_message ) )
        {
            throw new \InvalidArgumentException( 'JSON Error: ' . $_message );
        }

        return $_array;
    }

    /**
     * @param string $json
     *
     * @return string
     */
    public static function jsonToXml( $json )
    {
        return static::arrayToXml( static::jsonToArray( $json ) );
    }

    /**
     * @param string $json
     *
     * @return string
     */
    public static function jsonToCsv( $json )
    {
        return static::arrayToCsv( static::jsonToArray( $json ) );
    }

    /**
     * @param string $csv
     *
     * @return array
     */
    public static function csvToArray( $csv )
    {
        // currently need to write out to file to use parser
        $_tmpDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        $_filename = $_tmpDir . 'csv_import' . time() . '.csv';
        file_put_contents( $_filename, $csv );

        // assume first row is field header
        $_result = [ ];
        ini_set( 'auto_detect_line_endings', true );
        if ( ( $_handle = fopen( $_filename, "r" ) ) !== false )
        {
            $_headers = fgetcsv( $_handle, null, "," );
            while ( false !== ( $_row = fgetcsv( $_handle ) ) )
            {
                $_new = [ ];
                foreach ( $_headers as $_key => $_value )
                {
                    $_new[$_value] = ArrayUtils::get( $_row, $_key );
                }

                $_result[] = $_new;
            }

            fclose( $_handle );
            unlink( $_filename );
        }

        return $_result;
    }

    /**
     * @param string $csv
     *
     * @return string
     */
    public static function csvToJson( $csv )
    {
        return static::arrayToJson( static::csvToArray( $csv ) );
    }

    /**
     * @param string $csv
     *
     * @return string
     */
    public static function csvToXml( $csv )
    {
        return static::arrayToXml( static::csvToArray( $csv ) );
    }

    /**
     * @param array $array
     *
     * @return string
     */
    public static function arrayToJson( $array )
    {
        return static::jsonEncode( $array );
    }

    /**
     * @param array $array
     * @param bool  $suppress_empty
     *
     * @return string
     */
    public static function simpleArrayToXml( $array, $suppress_empty = false )
    {
        $xml = '';
        foreach ( $array as $key => $value )
        {
            $value = trim( $value, " " );
            if ( empty( $value ) and (bool)$suppress_empty )
            {
                continue;
            }
            $htmlValue = htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
            if ( $htmlValue != $value )
            {
                $xml .= "\t" . "<$key>$htmlValue</$key>\n";
            }
            else
            {
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
    public static function arrayToXml( $data, $root = null, $level = 1, $format = true )
    {
        $xml = null;
        if ( ArrayUtils::isArrayNumeric( $data ) )
        {
            foreach ( $data as $value )
            {
                $xml .= self::arrayToXml( $value, $root, $level, $format );
            }
        }
        else if ( ArrayUtils::isArrayAssociative( $data ) )
        {
            if ( !empty( $root ) )
            {
                if ( $format )
                {
                    $xml .= str_repeat( "\t", $level - 1 );
                }
                $xml .= "<$root>";
                if ( $format )
                {
                    $xml .= "\n";
                }
            }
            foreach ( $data as $key => $value )
            {
                $xml .= self::arrayToXml( $value, $key, $level + 1, $format );
            }
            if ( !empty( $root ) )
            {
                if ( $format )
                {
                    $xml .= str_repeat( "\t", $level - 1 );
                }
                $xml .= "</$root>";
                if ( $format )
                {
                    $xml .= "\n";
                }
            }
        }
        else if ( is_array( $data ) )
        {
            // empty array
        }
        else
        {
            // not an array
            if ( !empty( $root ) )
            {
                if ( $format )
                {
                    $xml .= str_repeat( "\t", $level - 1 );
                }
                $xml .= "<$root>";
                if ( !is_null( $data ) )
                {
                    if ( is_bool( $data ) )
                    {
                        $xml .= ( $data ) ? 'true' : 'false';
                    }
                    else if ( is_int( $data ) || is_float( $data ) )
                    {
                        $xml .= $data;
                    }
                    else if ( is_string( $data ) )
                    {
                        $htmlValue = htmlspecialchars( $data, ENT_QUOTES, 'UTF-8' );
                        $xml .= $htmlValue;
                    }
                }
                $xml .= "</$root>";
                if ( $format )
                {
                    $xml .= "\n";
                }
            }
        }

        return $xml;
    }

    /**
     * @param array $array
     *
     * @return string
     */
    public static function arrayToCsv( $array )
    {
        if ( !is_array( $array ) || empty( $array ) )
        {
            return '';
        }

        $_keys = array_keys( ArrayUtils::get( $array, 0, [ ] ) );
        $_data = $array;

        // currently need to write out to file to use parser
        $_tmpDir = rtrim( sys_get_temp_dir(), DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        $_filename = $_tmpDir . 'csv_export' . time() . '.csv';

        $_handle = fopen( $_filename, 'w' );

        // build header row
        fputcsv( $_handle, $_keys );

        foreach ( $_data as $_row )
        {
            foreach ( $_row as $_key => $_value )
            {
                // handle objects and array non-conformist to csv output
                if ( is_array( $_value ) || is_object( $_value ) )
                {
                    $_row[$_key] = json_encode( $_value );
                }
            }

            fputcsv( $_handle, $_row );
        }

        fclose( $_handle );
        $_csv = file_get_contents( $_filename );
        unlink( $_filename );

        return $_csv;
    }

    // other helpers

    /**
     * @param mixed $data Could be object, array, or simple type
     * @param bool  $prettyPrint
     *
     * @return null|string
     */
    public static function jsonEncode( $data, $prettyPrint = false )
    {
        $_data = static::export( $data );

        if ( version_compare( PHP_VERSION, '5.4', '>=' ) )
        {
            $_options = JSON_UNESCAPED_SLASHES | ( false !== $prettyPrint ? JSON_PRETTY_PRINT : 0 );

            return json_encode( $_data, $_options );
        }

        $_json = str_replace( '\/', '/', json_encode( $_data ) );

        return $prettyPrint ? static::pretty_json( $_json ) : $_json;
    }

    /**
     * Build the array from data.
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function export( $data )
    {
        if ( is_object( $data ) )
        {
            //	Allow embedded export method for specific export
            if ( $data instanceof Arrayable || method_exists( $data, 'toArray' ) )
            {
                $data = $data->toArray();
            }
            else
            {
                $data = get_object_vars( $data );
            }
        }

        if ( !is_array( $data ) )
        {
            return $data;
        }

        $_output = [ ];

        foreach ( $data as $_key => $_value )
        {
            $_output[$_key] = static::export( $_value );
        }

        return $_output;
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
    public static function pretty_json( $json )
    {
        $_result = null;
        $_pos = 0;
        $_length = strlen( $json );
        $_indentString = '  ';
        $_newLine = PHP_EOL;
        $_lastChar = null;
        $_outOfQuotes = true;

        for ( $_i = 0; $_i < $_length; $_i++ )
        {
            //	Grab the next character in the string.
            $_char = $json[$_i];

            // Put spaces around colons
            if ( $_outOfQuotes && ':' == $_char && ' ' != $_lastChar )
            {
                $_result .= ' ';
            }

            if ( $_outOfQuotes && ' ' != $_char && ':' == $_lastChar )
            {
                $_result .= ' ';
            }

            // Are we inside a quoted string?
            if ( '"' == $_char && '\\' != $_lastChar )
            {
                $_outOfQuotes = !$_outOfQuotes;
                // If this character is the end of an element,
                // output a new line and indent the next line.
            }
            else if ( ( $_char == '}' || $_char == ']' ) && $_outOfQuotes )
            {
                $_result .= $_newLine;
                $_pos--;
                for ( $_j = 0; $_j < $_pos; $_j++ )
                {
                    $_result .= $_indentString;
                }
            }

            //	Add the character to the result string.
            $_result .= $_char;

            //	If the last character was the beginning of an element output a new line and indent the next line.
            if ( ( ',' == $_char || '{' == $_char || '[' == $_char ) && $_outOfQuotes )
            {
                $_result .= $_newLine;

                if ( '{' == $_char || '[' == $_char )
                {
                    $_pos++;
                }

                for ( $_j = 0; $_j < $_pos; $_j++ )
                {
                    $_result .= $_indentString;
                }
            }

            $_lastChar = $_char;
        }

        return $_result;
    }

    /**
     * @param $error
     * @param $xml
     *
     * @return string
     */
    public static function displayXmlError( $error, $xml )
    {
        $return = $xml[$error->line - 1] . "\n";
        $return .= str_repeat( '-', $error->column ) . "^\n";

        switch ( $error->level )
        {
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

        $return .= trim( $error->message ) . "\n  Line: $error->line" . "\n  Column: $error->column";

        if ( $error->file )
        {
            $return .= "\n  File: $error->file";
        }

        return "$return\n\n--------------------------------------------\n\n";
    }

}