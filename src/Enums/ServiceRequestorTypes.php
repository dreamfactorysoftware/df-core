<?php
namespace DreamFactory\Core\Enums;

/**
 * Various service requestor types as bitmask-able values
 */
class ServiceRequestorTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int
     */
    const __default = self::NONE;

    /**
     * @var int No service requestor type is allowed
     */
    const NONE = 0;
    /**
     * @var int Service is being called from a client through the API
     */
    const API = 1; // 0b0001
    /**
     * @var int Service is being called from the scripting environment
     */
    const SCRIPT = 2; // 0b0010

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $requestorType
     *
     * @return string
     */
    public static function toNumeric($requestorType)
    {
        if (!is_string($requestorType)) {
            throw new \InvalidArgumentException('The requestor type "' . $requestorType . '" is not a string.');
        }

        return static::defines(strtoupper($requestorType), true);
    }

    /**
     * @param int $numericLevel
     *
     * @return string
     */
    public static function toString($numericLevel)
    {
        if (!is_numeric($numericLevel)) {
            throw new \InvalidArgumentException('The requestor type "' . $numericLevel . '" is not numeric.');
        }

        return static::nameOf($numericLevel);
    }
}
