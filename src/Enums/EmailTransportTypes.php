<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;
use DreamFactory\Core\Exceptions\NotImplementedException;

/**
 * Various Email Transport types
 */
class EmailTransportTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::SERVER_DEFAULT;
    /**
     * @var int Use whatever is configured in PHP, i.e. mail()
     */
    const SERVER_DEFAULT = 0;
    /**
     * @var int Use command line to be executed on the system, i.e. sendmail -f
     */
    const SERVER_COMMAND = 1;
    /**
     * @var int Use SMTP configuration provided
     */
    const SMTP = 2;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of names
     */
    protected static $strings = array(
        'Server Default' => self::SERVER_DEFAULT,
        'Server Command' => self::SERVER_COMMAND,
        'SMTP'           => self::SMTP,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $name
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toNumeric($name = null)
    {
        if (empty($name)) {
            return self::SERVER_DEFAULT;
        }

        if (!in_array(strtoupper($name), array_keys(array_change_key_case(static::$strings)))) {
            throw new NotImplementedException('The transport type "' . $name . '" is not supported.');
        }

        return static::defines(strtoupper($name), true);
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString($numericLevel = self::SERVER_DEFAULT)
    {
        if (!is_numeric($numericLevel)) {
            throw new \InvalidArgumentException('The transport type "' . $numericLevel . '" is not numeric.');
        }

        if (!in_array($numericLevel, static::$strings)) {
            throw new NotImplementedException('The transport type "' . $numericLevel . '" is not supported.');
        }

        return static::nameOf($numericLevel);
    }
}
