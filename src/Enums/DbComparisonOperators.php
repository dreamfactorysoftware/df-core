<?php
namespace DreamFactory\Core\Enums;


/**
 * DbComparisonOperators
 * DB server-side filter comparison operator string constants
 */
class DbComparisonOperators extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const EQ     = '=';
    const EQ_STR = 'EQ';
    /**
     * @var string
     */
    const NE     = '!=';
    const NE_2   = '<>';
    const NE_STR = 'NE';
    /**
     * @var string
     */
    const GT     = '>';
    const GT_STR = 'GT';
    /**
     * @var string
     */
    const GTE     = '>=';
    const GTE_STR = 'GTE';
    /**
     * @var string
     */
    const LT     = '<';
    const LT_STR = 'LT';
    /**
     * @var string
     */
    const LTE     = '<=';
    const LTE_STR = 'LTE';
    /**
     * @var string
     */
    const IN = 'IN';
    /**
     * @var string
     */
    const NOT_IN = 'NOT IN';
    /**
     * @var string
     */
    const ALL = 'ALL';
    /**
     * @var string
     */
    const LIKE = 'LIKE';
    /**
     * @var string
     */
    const STARTS_WITH = 'STARTS WITH';
    /**
     * @var string
     */
    const ENDS_WITH = 'ENDS WITH';
    /**
     * @var string
     */
    const CONTAINS = 'CONTAINS';
    /**
     * @var string
     */
    const IS_NULL = 'IS NULL';
    /**
     * @var string
     */
    const IS_NOT_NULL = 'IS NOT NULL';
    /**
     * @var string
     */
    const DOES_EXIST = 'DOES EXIST';
    /**
     * @var string
     */
    const DOES_NOT_EXIST = 'DOES NOT EXIST';

    public static function getServerSideFilterOperators()
    {
        return [
            static::EQ,
            static::NE,
            static::GT,
            static::GTE,
            static::LT,
            static::LTE,
            static::IN,
            static::NOT_IN,
            static::STARTS_WITH,
            static::ENDS_WITH,
            static::CONTAINS,
            static::IS_NULL,
            static::IS_NOT_NULL,
        ];
    }

    public static function getParsingOrder()
    {
        // Note: order matters here!
        return [
            static::GTE,
            static::LTE,
            static::NE,
            static::EQ,
            static::NE_2,
            static::GT,
            static::LT,
            static::GTE_STR,
            static::LTE_STR,
            static::NE_STR,
            static::EQ_STR,
            static::GT_STR,
            static::LT_STR,
            static::NOT_IN,
            static::IN,
            static::ALL,
            static::LIKE,
            static::STARTS_WITH,
            static::ENDS_WITH,
            static::CONTAINS,
            static::IS_NULL,
            static::IS_NOT_NULL,
        ];
    }

    public static function requiresValueList($op)
    {
        switch ($op){
            case static::IN:
            case static::NOT_IN:
            case static::ALL:
                return true;
            default:
                return false;
        }
    }

    public static function requiresNoValue($op)
    {
        switch ($op){
            case static::IS_NULL:
            case static::IS_NOT_NULL:
            case static::DOES_EXIST:
            case static::DOES_NOT_EXIST:
                return true;
            default:
                return false;
        }
    }
}
