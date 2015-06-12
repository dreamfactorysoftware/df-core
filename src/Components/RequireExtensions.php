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

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Exceptions\ServiceUnavailableException;

/**
 * Class RequireExtensions
 *
 * @package DreamFactory\Core\Components
 */
trait RequireExtensions
{
    /**
     * @param string|array $extensions
     *
     * @return bool Returns true if all required extensions are loaded, otherwise an exception is thrown
     * @throws ServiceUnavailableException
     */
    public static function checkExtensions( $extensions )
    {
        if ( empty( $extensions ) )
        {
            $extensions = [ ];
        }
        elseif ( is_string( $extensions ) )
        {
            $extensions = array_map( 'trim', explode( ',', trim( $extensions ) ) );
        }

        foreach ($extensions as $extension)
        {
            if ( !extension_loaded( $extension ) )
            {
                throw new ServiceUnavailableException( "Required extension or module '$extension' is not installed or loaded." );
            }
        }

        return true;
    }
}