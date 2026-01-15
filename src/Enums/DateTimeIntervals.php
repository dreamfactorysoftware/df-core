<?php
namespace DreamFactory\Core\Enums;

/**
 * Various date and time constants
 */
class DateTimeIntervals extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const __default = self::SECONDS_PER_MINUTE;

    /**
     * @var int Microseconds per hour
     */
    const US_PER_HOUR = 3600000;
    /**
     * @var int Microseconds per minute
     */
    const US_PER_MINUTE = 60000;
    /**
     * @var int Microseconds per second
     */
    const US_PER_SECOND = 1000000;
    /**
     * @var int Milliseconds per second
     */
    const MS_PER_SECOND = 1000;
    /**
     * @var int
     */
    const SECONDS_PER_MINUTE = 60;
    /**
     * @var int
     */
    const SECONDS_PER_HOUR = 3600;
    /**
     * @var int
     */
    const SECONDS_PER_DAY = 86400;
    /**
     * @var int
     */
    const SECONDS_PER_WEEK = 604800;
    /**
     * @var int
     */
    const SECONDS_PER_MONTH = 18144000;
    /**
     * @var int Stupid, I know, but at least it's a constant
     */
    const MINUTES_PER_MINUTE = 1;
    /**
     * @var int
     */
    const MINUTES_PER_HOUR = self::SECONDS_PER_MINUTE;
    /**
     * @var int
     */
    const MINUTES_PER_DAY = 1440;
    /**
     * @var int
     */
    const MINUTES_PER_WEEK = 10080;
    /**
     * @var int
     */
    const MINUTES_PER_MONTH = 43200;
    /**
     * @var int circa 01/01/1980 (Ahh... my TRS-80... good times)
     */
    const EPOCH_START = 315550800;
    /**
     * @var int circa 01/01/2038 (despite the Mayan calendar or John Titor...)
     */
    const EPOCH_END = 2145934800;

    //******************************************************************************
    //* Members
    //******************************************************************************

    /** @inheritdoc */
    protected static $tags = [
        self::MINUTES_PER_MINUTE => 'Minute',
        self::MINUTES_PER_HOUR   => 'Hour',
        self::MINUTES_PER_DAY    => 'Day',
        self::MINUTES_PER_WEEK   => 'Week',
        self::MINUTES_PER_MONTH  => 'Month',
    ];
}
