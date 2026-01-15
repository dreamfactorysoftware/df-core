<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Core\Exceptions\NotImplementedException;

/**
 * Various REST verbs as bitmask-able values
 */
class VerbsMask extends Verbs
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::NONE_MASK;

    /**
     * @var int No service requestor type is allowed
     */
    const NONE_MASK = 0;
    /**
     * @var int
     */
    const GET_MASK = 1;
    /**
     * @var int
     */
    const POST_MASK = 2;
    /**
     * @var int
     */
    const PUT_MASK = 4;
    /**
     * @var int
     */
    const PATCH_MASK = 8;
    /**
     * @var int
     */
    const DELETE_MASK = 16;
    /**
     * @var int
     */
    const OPTIONS_MASK = 32;
    /**
     * @var int
     */
    const HEAD_MASK = 64;
    /**
     * @var int
     */
    const COPY_MASK = 128;
    /**
     * @var int
     */
    const TRACE_MASK = 256;
    /**
     * @var int
     */
    const CONNECT_MASK = 512;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of level names
     */
    protected static $strings = array(
        self::GET     => self::GET_MASK,
        self::POST    => self::POST_MASK,
        self::PUT     => self::PUT_MASK,
        self::PATCH   => self::PATCH_MASK,
        self::DELETE  => self::DELETE_MASK,
        self::OPTIONS => self::OPTIONS_MASK,
        self::HEAD    => self::HEAD_MASK,
        self::COPY    => self::COPY_MASK,
        self::TRACE   => self::TRACE_MASK,
        self::CONNECT => self::CONNECT_MASK,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $requestorType
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toNumeric($requestorType = 'none')
    {
        if (!is_string($requestorType)) {
            throw new \InvalidArgumentException('The verb "' . $requestorType . '" is not a string.');
        }

        $requestorType = strtoupper($requestorType);
        if (!isset(static::$strings[$requestorType])) {
            throw new NotImplementedException('The verb "' . $requestorType . '" is not supported.');
        }

        return static::$strings[$requestorType];
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString($numericLevel = self::NONE_MASK)
    {
        if (!is_numeric($numericLevel)) {
            throw new \InvalidArgumentException('The verb mask "' . $numericLevel . '" is not numeric.');
        }

        if (false === $verb = array_search($numericLevel, static::$strings)) {
            throw new NotImplementedException('The verb mask "' . $numericLevel . '" is not supported.');
        }

        return $verb;
    }

    /**
     * @param array $array
     *
     * @return int
     */
    public static function arrayToMask($array)
    {
        $mask = self::NONE_MASK;

        if (empty($array) || !is_array($array)) {
            return $mask;
        }

        foreach ($array as $verb) {
            switch ($verb) {
                case self::GET:
                    $mask |= self::GET_MASK;
                    break;
                case self::POST:
                    $mask |= self::POST_MASK;
                    break;
                case self::PUT:
                    $mask |= self::PUT_MASK;
                    break;
                case self::PATCH:
                    $mask |= self::PATCH_MASK;
                    break;
                case self::DELETE:
                    $mask |= self::DELETE_MASK;
                    break;
                case self::OPTIONS:
                    $mask |= self::OPTIONS_MASK;
                    break;
                case self::HEAD:
                    $mask |= self::HEAD_MASK;
                    break;
                case self::COPY:
                    $mask |= self::COPY_MASK;
                    break;
                case self::TRACE:
                    $mask |= self::TRACE_MASK;
                    break;
                case self::CONNECT:
                    $mask |= self::CONNECT_MASK;
                    break;
            }
        }

        return $mask;
    }

    /**
     * @param int $mask
     *
     * @return string
     */
    public static function maskToArray($mask)
    {
        $array = array();

        if (empty($mask) || !is_int($mask)) {
            return $array;
        }

        if ($mask & self::GET_MASK) {
            $array[] = self::GET;
        }
        if ($mask & self::POST_MASK) {
            $array[] = self::POST;
        }
        if ($mask & self::PUT_MASK) {
            $array[] = self::PUT;
        }
        if ($mask & self::PATCH_MASK) {
            $array[] = self::PATCH;
        }
        if ($mask & self::DELETE_MASK) {
            $array[] = self::DELETE;
        }
        if ($mask & self::OPTIONS_MASK) {
            $array[] = self::OPTIONS;
        }
        if ($mask & self::HEAD_MASK) {
            $array[] = self::HEAD;
        }
        if ($mask & self::COPY_MASK) {
            $array[] = self::COPY;
        }
        if ($mask & self::TRACE_MASK) {
            $array[] = self::TRACE;
        }
        if ($mask & self::CONNECT_MASK) {
            $array[] = self::CONNECT;
        }

        return $array;
    }
}
