<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Core\Exceptions\NotImplementedException;

/**
 * Various Application storage types
 */
class AppTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::NONE;

    /**
     * @var int No storage defined (native ios, etc. application), default
     */
    const NONE = 0;
    /**
     * @var int Application files are located at a particular file storage service on this instance.
     */
    const STORAGE_SERVICE = 1;
    /**
     * @var int Application files are located at a particular URL
     * (i.e. http://example.com/index.html)
     */
    const URL = 2;
    /**
     * @var int Application files are located at a particular path of the public directory
     * (i.e. my_app/index.html)
     */
    const PATH = 3;
    /**
     * @var int Application files are located in a GIT repo, (i.e. github, bitbucket, etc.)
     */
    const GIT = 4;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $formatType
     *
     * @throws NotImplementedException
     * @throws \InvalidArgumentException
     * @return string
     */
    public static function toNumeric($formatType = 'none')
    {
        if (!is_string($formatType)) {
            throw new \InvalidArgumentException('The app type "' . $formatType . '" is not a string.');
        }

        return static::defines(strtoupper($formatType), true);
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @throws \InvalidArgumentException
     * @return string
     */
    public static function toString($numericLevel = self::NONE)
    {
        if (!is_numeric($numericLevel)) {
            throw new \InvalidArgumentException('The app type "' . $numericLevel . '" is not numeric.');
        }

        return static::nameOf($numericLevel);
    }
}
