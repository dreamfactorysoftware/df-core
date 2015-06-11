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

namespace DreamFactory\Rave\Components;

trait ApiVersion
{
    protected $apiVersion = null;

    /**
     * {@inheritdoc}
     */
    public function getApiVersion()
    {
        if ( empty( $this->apiVersion ) )
        {
            $this->setApiVersion();
        }

        return $this->apiVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function setApiVersion( $version = null )
    {
        if ( empty( $version ) )
        {
            $version = \Config::get( 'df.api_version' );
        }

        $version = strval( $version ); // if numbers are passed in
        if ( substr( strtolower( $version ), 0, 1 ) === 'v' )
        {
            $version = substr( $version, 1 );
        }
        if ( strpos( $version, '.' ) === false )
        {
            $version = $version . '.0';
        }

        $this->apiVersion = $version;
    }
}