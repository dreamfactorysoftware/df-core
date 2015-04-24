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
    protected $method = Verbs::GET;
    /**
     * @var array
     */
    protected $query = [ ];
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
    protected $contentType = ContentTypes::PHP_ARRAY;
    /**
     * @var array
     */
    protected $contentAsArray = [ ];

    public function __construct( $method = Verbs::GET, $query = [ ], $headers = [ ] )
    {
        $this->setMethod( $method );
        $this->setQuery( $query );
        $this->setHeaders( $headers );
    }

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
    public function setQuery( array $parameters )
    {
        $this->query = $parameters;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function query( $key = null, $default = null )
    {
        if ( null === $key )
        {
            return $this->query;
        }
        else
        {
        return ArrayUtils::get( $this->query, $key, $default );
    }
    }

    /**
     * @param mixed $key
     * @param bool  $default
     *
     * @return mixed
     */
    public function queryBool( $key, $default = false )
    {
        return ArrayUtils::getBool( $this->query, $key, $default );
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
     * @param null $key
     * @param null $default
     *
     * @return array|mixed
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
     * @param array $headers
     *
     * @return $this
     */
    public function setHeaders( array $headers )
    {
        $this->headers = $headers;

        return $this;
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
    public function getFile( $key = null, $default = null )
    {
        //Todo:Experiment Request::file()...
        return null;
    }
}