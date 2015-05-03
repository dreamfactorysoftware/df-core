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
use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Rave\Components\InternalServiceRequest;
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
    use InternalServiceRequest;

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
        if ( !empty( $this->method ) )
        {
            return $this->method;
        }

        return Request::getMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter( $key = null, $default = null )
    {
        if ( !empty( $this->parameters ) )
        {
            if ( null === $key )
            {
                return $this->parameters;
            }
            else
            {
                return ArrayUtils::get( $this->parameters, $key, $default );
            }
        }

        return Request::query( $key, $default );
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        if ( !empty( $this->parameters ) )
        {
            return $this->parameters;
        }

        return Request::query();
    }

    public function getParameterAsBool( $key, $default = false )
    {
        if ( !empty( $this->parameters ) )
        {
            return ArrayUtils::getBool( $this->parameters, $key, $default );
        }

        return Scalar::boolval( $this->getParameter( $key, $default ) );
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
        if ( !empty( $this->contentAsArray ) )
        {
            if ( null === $key )
            {
                return $this->contentAsArray;
            }
            else
            {
                return ArrayUtils::get( $this->contentAsArray, $key, $default );
            }
        }

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
        if ( !empty( $this->content ) )
        {
            return $this->content;
        }

        return Request::getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        if ( !empty( $this->contentType ) )
        {
            return $this->contentType;
        }

        return Request::getContentType();
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader( $key = null, $default = null )
    {
        if ( !empty( $this->headers ) )
        {
            if ( null === $key )
            {
                return $this->headers;
            }
            else
            {
                return ArrayUtils::get( $this->headers, $key, $default );
            }
        }

        if ( null === $key )
        {
            return Request::header();
        }
        else
        {
            return Request::header( $key, $default );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        if ( !empty( $this->headers ) )
        {
            return $this->headers;
        }

        return Request::header();
    }

    /**
     * {@inheritdoc}
     */
    public function getFile( $key = null, $default = null )
    {
        //Todo:Experiment Request::file()...
        if ( null === $key )
        {
            return $_FILES;
        }
        else
        {
            return ArrayUtils::get( $_FILES, $key, $default );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver()
    {
        return Request::instance();
    }
}