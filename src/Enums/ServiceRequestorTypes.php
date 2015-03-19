<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
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
     * @throws NotImplementedException
     * @return string
     */
    public static function toNumeric( $requestorType )
    {
        if ( !is_string( $requestorType ) )
        {
            throw new \InvalidArgumentException( 'The requestor type "' . $requestorType . '" is not a string.' );
        }

        return static::defines( strtoupper( $requestorType ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString( $numericLevel )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The requestor type "' . $numericLevel . '" is not numeric.' );
        }

        return static::nameOf( $numericLevel );
    }
}
