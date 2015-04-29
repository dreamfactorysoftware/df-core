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

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Enums\ContentTypes;

/**
 * Trait InternalServiceRequest
 *
 */
trait InternalServiceRequest
{
    use ApiVersion;

    /**
     * @var string
     */
    protected $method = null;
    /**
     * @var array
     */
    protected $parameters = [ ];
    /**
     * @var array
     */
    protected $headers = [ ];
    /**
     * @var null|string
     */
    protected $content = null;
    /**
     * @var null|string
     */
    protected $contentType = null;
    /**
     * @var array
     */
    protected $contentAsArray = [ ];

    /**
     * @param $verb
     *
     * @return $this
     * @throws \Exception
     */
    public function setMethod( $verb )
    {
        if ( !Verbs::contains( $verb ) )
        {
            throw new \Exception( "Invalid method '$verb'" );
        }

        $this->method = $verb;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param array $parameters
     *
     * @return $this
     */
    public function setParameters( array $parameters )
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter( $key, $value )
    {
        $this->parameters[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter( $key = null, $default = null )
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

    /**
     * @param mixed $key
     * @param bool  $default
     *
     * @return mixed
     */
    public function getParameterAsBool( $key, $default = false )
    {
        return ArrayUtils::getBool( $this->parameters, $key, $default );
    }

    /**
     * @param mixed $data
     * @param int   $type
     *
     * @return $this
     */
    public function setContent( $data, $type = ContentTypes::PHP_ARRAY )
    {
        $this->content = $data;
        $this->contentType = $type;

        switch ( $type )
        {
            case ContentTypes::PHP_ARRAY:
                $this->contentAsArray = $data;
                break;
            case ContentTypes::JSON:
                $this->contentAsArray = json_decode( $data, true );
                break;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setPayloadData( array $data )
    {
        $this->contentAsArray = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function setPayloadKeyValue( $key, $value )
    {
        $this->contentAsArray[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayloadData( $key = null, $default = null )
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

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders( array $headers )
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeader( $key, $data )
    {
        $this->headers[$key] = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader( $key = null, $default = null )
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

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * {@inheritdoc}
     */
    public function getFile( $key = null, $default = null )
    {
        //Todo:Experiment Request::file()...
        return null;
    }

    /**
     * @return array All attributes as an array
     */
    public function toArray()
    {
        return [
            'api_version'  => $this->getApiVersion(),
            'method'       => $this->getMethod(),
            'parameters'   => $this->getParameters(),
            'headers'      => $this->getHeaders(),
            'payload'      => $this->getPayloadData(),
            'content'      => $this->getContent(),
            'content_type' => $this->getContentType(),
        ];
    }

    /**
     * @param array $data Merge some attributes from an array
     */
    public function mergeFromArray( array $data )
    {
        $this->setMethod( ArrayUtils::get( $data, 'method' ) );
        $this->setParameters( ArrayUtils::get( $data, 'parameters' ) );
        $this->setHeaders( ArrayUtils::get( $data, 'headers' ) );
        $this->setPayloadData( ArrayUtils::get( $data, 'payload' ) );
        if (ArrayUtils::getBool( $data, 'content_changed' ))
        {
            $this->setContent( ArrayUtils::get( $data, 'content' ), ArrayUtils::get( $data, 'content_type' ) );
        }
    }
}