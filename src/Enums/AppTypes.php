<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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
namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;
use DreamFactory\Core\Exceptions\NotImplementedException;

/**
 * Various Application storage types
 */
class AppTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    const __default = self::NONE;

    /**
     * @var int No storage defined (native ios, etc. application), default
     */
    const NONE = 0;
    /**
     * @var int Application files are located at a particular file storage service on this DSP.
     */
    const STORAGE_SERVICE = 1;
    /**
     * @var int Application files are located at a particular URL
     * (i.e. http://example.com/index.html)
     */
    const URL = 2;
    /**
     * @var int Application files are located at a particular path of the public directory
     * (i.e. my_app/index.html)
     */
    const PATH = 3;
    /**
     * @var int Application files are located in a GIT repo, (i.e. github, bitbucket, etc.)
     */
    const GIT = 4;

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
    public static function toNumeric( $formatType = 'none' )
    {
        if ( !is_string( $formatType ) )
        {
            throw new \InvalidArgumentException( 'The app type "' . $formatType . '" is not a string.' );
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
    public static function toString( $numericLevel = self::NONE )
    {
        if ( !is_numeric( $numericLevel ) )
        {
            throw new \InvalidArgumentException( 'The app type "' . $numericLevel . '" is not numeric.' );
        }

        return static::nameOf( $numericLevel );
    }
}
