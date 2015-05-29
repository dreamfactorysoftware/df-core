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

interface ServiceResponseInterface extends HttpStatusCodeInterface
{
    /**
     * @param $code int Http Status code
     *
     * @return ServiceResponseInterface
     */
    public function setStatusCode( $code );

    /**
     * @return int Http Status code
     */
    public function getStatusCode();

    /**
     * @param $content mixed Response content
     *
     * @return ServiceResponseInterface
     */
    public function setContent( $content );

    /**
     * @return mixed Response content
     */
    public function getContent();

    /**
     * @param $type string Content Type (i.e. MIME type)
     *
     * @return ServiceResponseInterface
     */
    public function setContentType( $type );

    /**
     * @return null|string Content Type (i.e. MIME type) or null if not set
     */
    public function getContentType();

    /**
     * @param $format int DataFormats
     *
     * @return ServiceResponseInterface
     */
    public function setContentFormat( $format );

    /**
     * @return int DataFormats
     */
    public function getContentFormat();

    /**
     * @return array All attributes as an array
     */
    public function toArray();

    /**
     * @param array $data Merge some attributes from an array
     */
    public function mergeFromArray(array $data);
}