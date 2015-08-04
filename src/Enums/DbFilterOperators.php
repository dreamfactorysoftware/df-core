<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * DbFilterOperators
 * DB server-side filter operator string constants
 */
class DbFilterOperators extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const EQ = '=';
    /**
     * @var string
     */
    const NE = '!=';
    /**
     * @var string
     */
    const GT = '>';
    /**
     * @var string
     */
    const GE = '>=';
    /**
     * @var string
     */
    const LT = '<';
    /**
     * @var string
     */
    const LE = '<=';
    /**
     * @var string
     */
    const IN = 'in';
    /**
     * @var string
     */
    const NOT_IN = 'not in';
    /**
     * @var string
     */
    const STARTS_WITH = 'starts with';
    /**
     * @var string
     */
    const ENDS_WITH = 'ends with';
    /**
     * @var string
     */
    const CONTAINS = 'contains';
    /**
     * @var string
     */
    const IS_NULL = 'is null';
    /**
     * @var string
     */
    const IS_NOT_NULL = 'is not null';
    /**
     * @var string
     */
    const DOES_EXIST = 'does exist';
    /**
     * @var string
     */
    const DOES_NOT_EXIST = 'does not exist';

}
