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
    protected static $_strings = array(
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
    public static function toNumeric( $name = null )
    {
        if ( empty( $name ) )
        {
            return self::SERVER_DEFAULT;
        }

        if ( !in_array( strtoupper( $name ), array_keys( array_change_key_case( static::$_strings ) ) ) )
        {
            throw new NotImplementedException( 'The transport type "' . $name . '" is not supported.' );
        }

        return static::defines( strtoupper( $name ), true );
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString( $numericLevel = self::SERVER_DEFAULT )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The transport type "' . $numericLevel . '" is not numeric.' );
        }

        if ( !in_array( $numericLevel, static::$_strings ) )
        {
            throw new NotImplementedException( 'The transport type "' . $numericLevel . '" is not supported.' );
        }

        return static::nameOf( $numericLevel );
    }
}
