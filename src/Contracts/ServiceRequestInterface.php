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

namespace DreamFactory\Rave\Contracts;

use DreamFactory\Rave\Enums\ContentTypes;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ServiceRequestInterface
{
    /**
     * @return string HTTP Verb
     */
    public function getMethod();

    /**
     * @param string $method HTTP Verb
     */
    public function setMethod( $method );

    /**
     * @return array
     */
    public function getParameters();

    /**
     * @param mixed $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function getParameter( $key = null, $default = null );

    /**
     * @param mixed $key
     * @param bool  $default
     *
     * @return boolean
     */
    public function getParameterAsBool( $key, $default = false );

    /**
     * @param array $parameters
     */
    public function setParameters( array $parameters );

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function setParameter( $key, $value );

    /**
     * @param null|string $key
     * @param null|string $default
     *
     * @return array
     */
    public function getPayloadData( $key = null, $default = null );

    /**
     * @param array $data
     */
    public function setPayloadData( array $data );

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function setPayloadKeyValue( $key, $value );

    /**
     * @return mixed
     */
    public function getContent();

    /**
     * @return string
     */
    public function getContentType();

    /**
     * @param mixed $content
     * @param int   $type
     */
    public function setContent( $content, $type = ContentTypes::PHP_ARRAY );

    /**
     * @return array
     */
    public function getHeaders();

    /**
     * @param null|string $key
     * @param null|string $default
     *
     * @return mixed
     */
    public function getHeader( $key = null, $default = null );

    /**
     * @param array $headers
     */
    public function setHeaders( array $headers );

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function setHeader( $key, $value );

    /**
     * Retrieve a file from the request.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return UploadedFile|array
     */
    public function getFile( $key = null, $default = null );

    /**
     * Retrieve API version
     *
     * @return string
     */
    public function getApiVersion();

    /**
     * Retrieve requestor type, see ServiceRequestorTypes
     *
     * @return integer
     */
    public function getRequestorType();

    /**
     * @return array All attributes as an array
     */
    public function toArray();

    /**
     * @param array $data Merge some attributes from an array
     */
    public function mergeFromArray( array $data );

    /**
     * Returns the underlying Request object if any that handles the
     * HTTP requests.
     *
     * @return mixed
     */
    public function getDriver();
}