<?php
/**
 * This file is part of the DreamFactory Rave(tm) Common
 *
 * DreamFactory Rave(tm) Common <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Rave\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;
use DreamFactory\Rave\Exceptions\NotImplementedException;

/**
 * Various API Documentation Format types
 */
class ApiDocFormatTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::SWAGGER;

    /**
     * @var int Swagger json format, default
     */
    const SWAGGER = 0;
    /**
     * @var int RAML, RESTful API modeling language
     */
    const RAML = 1;
    /**
     * @var int API Blueprint format
     */
    const API_BLUEPRINT = 2;
    /**
     * @var int Pipe-separated values
     */
    const IO_DOCS = 3;

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
    public static function toNumeric( $formatType = 'swagger' )
    {
        if ( !is_string( $formatType ) )
        {
            throw new \InvalidArgumentException( 'The format type "' . $formatType . '" is not a string.' );
        }

        return static::defines( strtoupper( $formatType ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @throws \InvalidArgumentException
     * @return string
     */
    public static function toString( $numericLevel = self::SWAGGER )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The format type "' . $numericLevel . '" is not numeric.' );
        }

        return static::nameOf( $numericLevel );
    }
}
