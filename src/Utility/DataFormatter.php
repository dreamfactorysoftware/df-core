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

/**
 * Class DataFormatter
 *
 * @package DreamFactory\Rave\Utility
 */
class DataFormatter
{
    /**
     * @param array $array
     *
     * @return string
     */
    public static function arrayToJson( $array )
    {
        return json_encode( $array );
    }

    /**
     * @param $array
     *
     * @return bool
     */
    public static function isArrayNumeric( $array )
    {
        if ( is_array( $array ) )
        {
            if ( !empty( $array ) )
            {
                return ( 0 === count( array_filter( array_keys( $array ), 'is_string' ) ) );
            }
        }

        return false;
    }
}