<?php
namespace DreamFactory\Core\Enums;


/**
 * DbLogicalOperators
 * DB server-side filter logical operator string constants
 */
class DbLogicalOperators extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const AND_SYM = '&&';
    const AND_STR = 'AND';
    /**
     * @var string
     */
    const OR_SYM = '||';
    const OR_STR = 'OR';
    /**
     * @var string
     */
    const NOR_STR = 'NOR';
    /**
     * @var string
     */
    const XOR_STR = 'XOR';
    /**
     * @var string
     */
    const NOT_STR = 'NOT';
}
