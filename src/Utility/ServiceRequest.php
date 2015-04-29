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

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Components\ApiVersion;
use DreamFactory\Rave\Enums\ServiceRequestorTypes;
use DreamFactory\Rave\Exceptions\BadRequestException;
use Request;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;

/**
 * Class ServiceRequest
 *
 * @package DreamFactory\Rave\Utility
 */
class ServiceRequest implements ServiceRequestInterface
{
    use ApiVersion;

    /**
     * {@inheritdoc}
     */
    public function getRequestorType()
    {
        return ServiceRequestorTypes::API;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return Request::getMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function query( $key = null, $default = null )
    {
        //query is cached internally using parameterBag.
        return Request::query( $key, $default );
    }

    public function queryBool( $key, $default = false )
    {
        return ArrayUtils::getBool( $this->query(), $key, $default );
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return array
     * @throws BadRequestException
     */
    public function getPayloadData( $key = null, $default = null )
    {
        //This just checks the Request Header Content-Type.
        if ( Request::isJson() )
        {
            //Decoded json data is cached internally using parameterBag.
            return $this->json( $key, $default );
        }

        //Check the actual content. If it is blank return blank array.
        $content = $this->getContent();
        if ( empty( $content ) )
        {
            return [ ];
        }

        //Checking this last to be more efficient.
        if ( json_decode( $content ) !== null )
        {
            //Decoded json data is cached internally using parameterBag.
            return $this->json( $key, $default );
        }

        //Todo:Check for additional content-type here.

        throw new BadRequestException( 'Unrecognized payload type' );
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return mixed
     */
    protected function json( $key = null, $default = null )
    {
        if ( null === $key )
        {
            return Request::json()->all();
        }
        else
        {
            return Request::json( $key, $default );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return Request::getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($key=null, $default=null)
    {
        if(null===$key)
        {
            return Request::header();
        }
        else{
            return Request::header($key, $default);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getFile($key=null, $default=null)
    {
        //Todo:Experiment Request::file()...
        if(null===$key)
        {
            return $_FILES;
        }
        else
        {
            return ArrayUtils::get($_FILES, $key, $default);
        }
    }
}