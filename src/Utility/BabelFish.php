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
namespace DreamFactory\Rave\Utility;

use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Enums\DataFormats;

/**
 * Universal data translator
 */
class BabelFish
{
    /**
     * @param int   $from       The format of the information
     * @param int   $to         The format to translate to
     * @param mixed $subject    THe information to translate
     * @param mixed $translated Holds the translated subject value
     *
     * @return bool
     * @throws BadRequestException
     */
    public static function translate( $from, $to, $subject, &$translated )
    {
        $_result = true;
        $translated = $subject;

        if ( !DataFormats::contains( $from ) || !DataFormats::contains( $to ) )
        {
            throw new BadRequestException( 'Invalid data format specified' );
        }

        //  Translate!
        switch ( $from )
        {
            //  PHP array & object
            case DataFormats::PHP_ARRAY:
            case DataFormats::PHP_OBJECT:
                switch ( $to )
                {
                    //  JSON string
                    case DataFormats::JSON:
                        if ( false === ( $translated = json_encode( $subject, JSON_UNESCAPED_SLASHES ) ) )
                        {
                            if ( JSON_ERROR_NONE !== json_last_error() )
                            {
                                $_result = false;
                                $translated = $subject;
                            }
                        }
                        break;

                    default:
                        $_result = false;
                        break;
                }
                break;

            //  JSON string
            case DataFormats::JSON:
                switch ( $to )
                {
                    //  PHP array & object
                    case DataFormats::PHP_ARRAY:
                    case DataFormats::PHP_OBJECT:
                        if ( false === ( $translated = json_decode( $subject, ( DataFormats::PHP_ARRAY == $from ) ) ) )
                        {
                            if ( JSON_ERROR_NONE !== json_last_error() )
                            {
                                $translated = $subject;
                                $_result = false;
                            }
                        }
                        break;

                    default:
                        $_result = false;
                        break;
                }
                break;

            default:
                $_result = false;
                break;
        }

        return $_result;
    }
}