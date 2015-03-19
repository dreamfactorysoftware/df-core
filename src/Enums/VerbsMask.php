<?php
/**
 * This file is part of the DreamFactory Rave(tm) Common
 *
 * DreamFactory Rave(tm) Common <http://github.com/dreamfactorysoftware/rave-common>
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

use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Exceptions\NotImplementedException;

/**
 * Various REST verbs as bitmask-able values
 */
class VerbsMask extends Verbs
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::NONE_MASK;

    /**
     * @var int No service requestor type is allowed
     */
    const NONE_MASK = 0;
    /**
     * @var int
     */
    const GET_MASK = 1;
    /**
     * @var int
     */
    const POST_MASK = 2;
    /**
     * @var int
     */
    const PUT_MASK = 4;
    /**
     * @var int
     */
    const PATCH_MASK = 8;
    /**
     * @var int
     */
    const MERGE_MASK = 16;
    /**
     * @var int
     */
    const DELETE_MASK = 32;
    /**
     * @var int
     */
    const OPTIONS_MASK = 64;
    /**
     * @var int
     */
    const HEAD_MASK = 128;
    /**
     * @var int
     */
    const COPY_MASK = 256;
    /**
     * @var int
     */
    const TRACE_MASK = 512;
    /**
     * @var int
     */
    const CONNECT_MASK = 1024;

    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var array A hash of level names
     */
    protected static $_strings = array(
        self::GET     => self::GET_MASK,
        self::POST    => self::POST_MASK,
        self::PUT     => self::PUT_MASK,
        self::PATCH   => self::PATCH_MASK,
        self::MERGE   => self::MERGE_MASK,
        self::DELETE  => self::DELETE_MASK,
        self::OPTIONS => self::OPTIONS_MASK,
        self::HEAD    => self::HEAD_MASK,
        self::COPY    => self::COPY_MASK,
        self::TRACE   => self::TRACE_MASK,
        self::CONNECT => self::CONNECT_MASK,
    );

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string $requestorType
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toNumeric( $requestorType = 'none' )
    {
        if ( !is_string( $requestorType ) )
        {
            throw new \InvalidArgumentException( 'The verb "' . $requestorType . '" is not a string.' );
        }

        $requestorType = strtoupper( $requestorType );
        if ( !isset( static::$_strings[$requestorType] ) )
        {
            throw new NotImplementedException( 'The verb "' . $requestorType . '" is not supported.' );
        }

        return static::$_strings[$requestorType];
    }

    /**
     * @param int $numericLevel
     *
     * @throws NotImplementedException
     * @return string
     */
    public static function toString( $numericLevel = self::NONE_MASK )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The verb mask "' . $numericLevel . '" is not numeric.' );
        }

        if ( false === $_verb = array_search( $numericLevel, static::$_strings ) )
        {
            throw new NotImplementedException( 'The verb mask "' . $numericLevel . '" is not supported.' );
        }

        return $_verb;
    }

    /**
     * @param array $array
     *
     * @return int
     */
    public static function arrayToMask( $array )
    {
        $_mask = self::NONE_MASK;

        if ( empty( $array ) || !is_array( $array ) )
        {
            return $_mask;
        }

        foreach ( $array as $_verb )
        {
            switch ( $_verb )
            {
                case self::GET:
                    $_mask |= self::GET_MASK;
                    break;
                case self::POST:
                    $_mask |= self::POST_MASK;
                    break;
                case self::PUT:
                    $_mask |= self::PUT_MASK;
                    break;
                case self::PATCH:
                    $_mask |= self::PATCH_MASK;
                    break;
                case self::MERGE:
                    $_mask |= self::MERGE_MASK;
                    break;
                case self::DELETE:
                    $_mask |= self::DELETE_MASK;
                    break;
                case self::OPTIONS:
                    $_mask |= self::OPTIONS_MASK;
                    break;
                case self::HEAD:
                    $_mask |= self::HEAD_MASK;
                    break;
                case self::COPY:
                    $_mask |= self::COPY_MASK;
                    break;
                case self::TRACE:
                    $_mask |= self::TRACE_MASK;
                    break;
                case self::CONNECT:
                    $_mask |= self::CONNECT_MASK;
                    break;
            }
        }

        return $_mask;
    }

    /**
     * @param int $mask
     *
     * @return string
     */
    public static function maskToArray( $mask )
    {
        $_array = array();

        if ( empty( $mask ) || !is_int( $mask ) )
        {
            return $_array;
        }

        if ( $mask & self::GET_MASK )
        {
            $_array[] = self::GET;
        }
        if ( $mask & self::POST_MASK )
        {
            $_array[] = self::POST;
        }
        if ( $mask & self::PUT_MASK )
        {
            $_array[] = self::PUT;
        }
        if ( $mask & self::PATCH_MASK )
        {
            $_array[] = self::PATCH;
        }
        if ( $mask & self::MERGE_MASK )
        {
            $_array[] = self::MERGE;
        }
        if ( $mask & self::DELETE_MASK )
        {
            $_array[] = self::DELETE;
        }
        if ( $mask & self::OPTIONS_MASK )
        {
            $_array[] = self::OPTIONS;
        }
        if ( $mask & self::HEAD_MASK )
        {
            $_array[] = self::HEAD;
        }
        if ( $mask & self::COPY_MASK )
        {
            $_array[] = self::COPY;
        }
        if ( $mask & self::TRACE_MASK )
        {
            $_array[] = self::TRACE;
        }
        if ( $mask & self::CONNECT_MASK )
        {
            $_array[] = self::CONNECT;
        }

        return $_array;
    }
}
