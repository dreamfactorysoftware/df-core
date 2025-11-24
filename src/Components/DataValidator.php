<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\BadRequestException;

/**
 * Class DataValidator
 *
 * @package DreamFactory\Core\Components
 */
trait DataValidator
{
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
        if (is_string($data) && ('' !== $data) && (is_string($str_delimiter) && !empty($str_delimiter))) {
            $data = array_map('trim', explode($str_delimiter, trim($data, $str_delimiter)));
        }

        if (is_int($data)) {
            $data = [$data]; // make an array of it
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

}