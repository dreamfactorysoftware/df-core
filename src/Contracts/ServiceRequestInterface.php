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

interface ServiceRequestInterface
{
    const API_VERSION = '2.0';
    /**
     * @return string Http Verbs
     */
    public function getMethod();

    /**
     * @param mixed $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function query( $key = null, $default = null );

    /**
     * @param mixed $key
     * @param bool  $default
     *
     * @return boolean
     */
    public function queryBool( $key, $default = false );

    /**
     * @param null|string $key
     * @param null|string $default
     *
     * @return array
     */
    public function getPayloadData( $key = null, $default = null );

    /**
     * @return mixed
     */
    public function getContent();

    /**
     * @param null|string $key
     * @param null|string $default
     *
     * @return mixed
     */
    public function getHeader($key=null, $default = null);

    /**
     * Retrieve a file from the request.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return UploadedFile|array
     */
    public function getFile($key=null, $default = null);

    /**
     * Retrieve api version
     *
     * @return string
     */
    public function getApiVersion();

    /**
     * @param string|null $version
     *
     * @return mixed
     */
    public function setApiVersion($version=null);
}