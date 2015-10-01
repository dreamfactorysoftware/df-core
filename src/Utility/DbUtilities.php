<?php
namespace DreamFactory\Core\Utility;

use Config;
use DreamFactory\Library\Utility\Scalar;
use Log;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\BadRequestException;

/**
 * DbUtilities
 * Generic database utilities
 */
class DbUtilities
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param $avail_fields
     *
     * @return string
     */
    public static function getPrimaryKeyFieldFromDescribe($avail_fields)
    {
        foreach ($avail_fields as $field_info) {
            if ($field_info->isPrimaryKey) {
                return $field_info->name;
            }
        }

        return '';
    }

    /**
     * @param array   $avail_fields
     * @param boolean $names_only Return only an array of names, otherwise return all properties
     *
     * @return array
     */
    public static function getPrimaryKeys($avail_fields, $names_only = false)
    {
        $keys = [];
        foreach ($avail_fields as $info) {
            if ($info->isPrimaryKey) {
                $keys[] = ($names_only ? $info->name : $info);
            }
        }

        return $keys;
    }

    /**
     * @param array $original
     *
     * @return array
     */
    public static function reformatFieldLabelArray($original)
    {
        if (empty($original)) {
            return [];
        }

        $new = [];
        foreach ($original as $label) {
            $new[ArrayUtils::get($label, 'field')] = $label;
        }

        return $new;
    }

    /**
     * @param $type
     *
     * @return null|string
     */
    public static function determinePhpConversionType($type)
    {
        switch ($type) {
            case 'boolean':
                return 'bool';

            case 'integer':
            case 'id':
            case 'reference':
            case 'user_id':
            case 'user_id_on_create':
            case 'user_id_on_update':
                return 'int';

            case 'decimal':
            case 'float':
            case 'double':
                return 'float';

            case 'string':
            case 'text':
                return 'string';

            // special checks
            case 'date':
                return 'date';

            case 'time':
                return 'time';

            case 'datetime':
                return 'datetime';

            case 'timestamp':
            case 'timestamp_on_create':
            case 'timestamp_on_update':
                return 'timestamp';
        }

        return null;
    }

    /**
     * @param array | string $data          Array to check or comma-delimited string to convert
     * @param string | null  $str_delimiter Delimiter to check for string to array mapping, no op if null
     * @param boolean        $check_single  Check if single (associative) needs to be made multiple (numeric)
     * @param string | null  $on_fail       Error string to deliver in thrown exception
     *
     * @throws BadRequestException
     * @return array | boolean If requirements not met then throws exception if
     * $on_fail string given, or returns false. Otherwise returns valid array
     */
    public static function validateAsArray($data, $str_delimiter = null, $check_single = false, $on_fail = null)
    {
        if (!empty($data) && !is_array($data) && (is_string($str_delimiter) && !empty($str_delimiter))) {
            $data = array_map('trim', explode($str_delimiter, trim($data, $str_delimiter)));
        }

        if (!is_array($data) || empty($data)) {
            if (!is_string($on_fail) || empty($on_fail)) {
                return false;
            }

            throw new BadRequestException($on_fail);
        }

        if ($check_single) {
            if (!isset($data[0])) {
                // single record possibly passed in without wrapper array
                $data = [$data];
            }
        }

        return $data;
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
                return Scalar::boolval($value);

            case 'string':
                return strval($value);

            case 'time':
            case 'date':
            case 'datetime':
            case 'timestamp':
                $cfgFormat = static::getDateTimeFormat($type);

                return static::formatDateTime($cfgFormat, $value);
        }

        return $value;
    }

    public static function getDateTimeFormat($type)
    {
        switch (strtolower(strval($type))) {
            case 'time':
                return Config::get('df.db_time_format');

            case 'date':
                return Config::get('df.db_date_format');

            case 'datetime':
                return Config::get('df.db_datetime_format');

            case 'timestamp':
                return Config::get('df.db_timestamp_format');
        }

        return null;
    }

    public static function formatDateTime($out_format, $in_value = null, $in_format = null)
    {
        //  If value is null, current date and time are returned
        if (!empty($out_format)) {
            $in_value = (is_string($in_value) || is_null($in_value)) ? $in_value : strval($in_value);
            if (!empty($in_format)) {
                if (false === $date = \DateTime::createfromFormat($in_format, $in_value)) {
                    Log::error("Failed to format datetime from '$in_value'' to '$in_format'");

                    return $in_value;
                }
            } else {
                $date = new \DateTime($in_value);
            }

            return $date->format($out_format);
        }

        return $in_value;
    }

    public static function findRecordByNameValue($data, $field, $value)
    {
        foreach ($data as $record) {
            if (ArrayUtils::get($record, $field) === $value) {
                return $record;
            }
        }

        return null;
    }

    /**
     * @param array        $record
     * @param string|array $include  List of keys to include in the output record
     * @param string|array $id_field Single or list of identifier fields
     *
     * @return array
     */
    protected static function cleanRecord($record = [], $include = '*', $id_field = null)
    {
        if ('*' !== $include) {
            if (!empty($id_field) && !is_array($id_field)) {
                $id_field = array_map('trim', explode(',', trim($id_field, ',')));
            }
            $id_field = ArrayUtils::clean($id_field);

            if (!empty($include) && !is_array($include)) {
                $include = array_map('trim', explode(',', trim($include, ',')));
            }
            $include = ArrayUtils::clean($include);

            // make sure we always include identifier fields
            foreach ($id_field as $id) {
                if (false === array_search($id, $include)) {
                    $include[] = $id;
                }
            }

            // glean desired fields from record
            $out = [];
            foreach ($include as $key) {
                $out[$key] = ArrayUtils::get($record, $key);
            }

            return $out;
        }

        return $record;
    }

    /**
     * @param array $records
     * @param mixed $include
     * @param mixed $id_field
     *
     * @return array
     */
    protected static function cleanRecords($records, $include = '*', $id_field = null)
    {
        $out = [];
        foreach ($records as $record) {
            $out[] = static::cleanRecord($record, $include, $id_field);
        }

        return $out;
    }

    /**
     * @param array  $record
     * @param string $id_field
     * @param bool   $include_field
     * @param bool   $remove
     *
     * @throws BadRequestException
     * @return array
     */
    protected static function recordAsId(&$record, $id_field = null, $include_field = false, $remove = false)
    {
        if (empty($id_field)) {
            return [];
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        if (count($id_field) > 1) {
            $ids = [];
            foreach ($id_field as $field) {
                $id = ArrayUtils::get($record, $field, null, $remove);
                if (empty($id)) {
                    throw new BadRequestException("Identifying field '$field' can not be empty for record.");
                }
                $ids[$field] = $id;
            }

            return $ids;
        } else {
            $field = $id_field[0];
            $id = ArrayUtils::get($record, $field, null, $remove);
            if (empty($id)) {
                throw new BadRequestException("Identifying field '$field' can not be empty for record.");
            }

            return ($include_field) ? [$field => $id] : $id;
        }
    }

    /**
     * @param        $ids
     * @param string $id_field
     * @param bool   $field_included
     *
     * @return array
     */
    protected static function idsAsRecords($ids, $id_field, $field_included = false)
    {
        if (empty($id_field)) {
            return [];
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        $out = [];
        foreach ($ids as $id) {
            $ids = [];
            if ((count($id_field) > 1) && (count($id) > 1)) {
                foreach ($id_field as $index => $field) {
                    $search = ($field_included) ? $field : $index;
                    $ids[$field] = ArrayUtils::get($id, $search);
                }
            } else {
                $field = $id_field[0];
                $ids[$field] = $id;
            }

            $out[] = $ids;
        }

        return $out;
    }

    /**
     * @param array $record
     * @param array $id_field
     */
    protected static function removeIds(&$record, $id_field)
    {
        if (!empty($id_field)) {

            if (!is_array($id_field)) {
                $id_field = array_map('trim', explode(',', trim($id_field, ',')));
            }

            foreach ($id_field as $name) {
                unset($record[$name]);
            }
        }
    }

    /**
     * @param      $record
     * @param null $id_field
     *
     * @return bool
     */
    protected static function containsIdFields($record, $id_field = null)
    {
        if (empty($id_field)) {
            return false;
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        foreach ($id_field as $field) {
            $temp = ArrayUtils::get($record, $field);
            if (empty($temp)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param        $fields
     * @param string $id_field
     *
     * @return bool
     */
    protected static function requireMoreFields($fields, $id_field = null)
    {
        if (('*' == $fields) || empty($id_field)) {
            return true;
        }

        if (false === $fields = DbUtilities::validateAsArray($fields, ',')) {
            return false;
        }

        if (!is_array($id_field)) {
            $id_field = array_map('trim', explode(',', trim($id_field, ',')));
        }

        foreach ($id_field as $key => $name) {
            if (false !== array_search($name, $fields)) {
                unset($fields[$key]);
            }
        }

        return !empty($fields);
    }

    /**
     * @param        $first_array
     * @param        $second_array
     * @param string $id_field
     *
     * @return mixed
     */
    protected static function recordArrayMerge($first_array, $second_array, $id_field = null)
    {
        if (empty($id_field)) {
            return [];
        }

        foreach ($first_array as $key => $first) {
            $firstId = ArrayUtils::get($first, $id_field);
            foreach ($second_array as $second) {
                $secondId = ArrayUtils::get($second, $id_field);
                if ($firstId == $secondId) {
                    $first_array[$key] = array_merge($first, $second);
                }
            }
        }

        return $first_array;
    }

    /**
     * @param $value
     *
     * @return bool|int|null|string
     */
    public static function interpretFilterValue($value)
    {
        // all other data types besides strings, just return
        if (!is_string($value) || empty($value)) {
            return $value;
        }

        $end = strlen($value) - 1;
        // filter string values should be wrapped in matching quotes
        if (((0 === strpos($value, '"')) && ($end === strrpos($value, '"'))) ||
            ((0 === strpos($value, "'")) && ($end === strrpos($value, "'")))
        ) {
            return substr($value, 1, $end - 1);
        }

        // check for boolean or null values
        switch (strtolower($value)) {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        if (is_numeric($value)) {
            return $value + 0; // trick to get int or float
        }

        // the rest should be lookup keys, or plain strings
        Session::replaceLookups( $value );
        return $value;
    }

    /**
     * @param array $record
     *
     * @return array
     */
    public static function interpretRecordValues($record)
    {
        if (!is_array($record) || empty($record)) {
            return $record;
        }

        foreach ($record as $field => $value) {
            Session::replaceLookups( $value );
            $record[$field] = $value;
        }

        return $record;
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function startsWith($haystack, $needle)
    {
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }

    /**
     * @param $haystack
     * @param $needle
     *
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        return (substr($haystack, -strlen($needle)) === $needle);
    }
}
