<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Core\Exceptions\NotImplementedException;

/**
 * Various API Documentation Format types
 */
class ApiDocFormatTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::SWAGGER_JSON;

    /**
     * @var int Swagger json format, default
     */
    const SWAGGER_JSON = 0;
    /**
     * @var int Swagger yaml format
     */
    const SWAGGER_YAML = 1;
    /**
     * @var int RAML, RESTful API modeling language
     */
    const RAML = 2;
    /**
     * @var int API Blueprint format
     */
    const API_BLUEPRINT = 3;
    /**
     * @var int IO Docs format
     */
    const IO_DOCS = 4;

    //*************************************************************************
    //* Members
    //*************************************************************************

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
    public static function toNumeric($formatType = 'SWAGGER_JSON')
    {
        if (!is_string($formatType)) {
            throw new \InvalidArgumentException('The format type "' . $formatType . '" is not a string.');
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
    public static function toString($numericLevel = self::SWAGGER_JSON)
    {
        if (!is_numeric($numericLevel)) {
            throw new \InvalidArgumentException('The format type "' . $numericLevel . '" is not numeric.');
        }

        return static::nameOf($numericLevel);
    }
}
