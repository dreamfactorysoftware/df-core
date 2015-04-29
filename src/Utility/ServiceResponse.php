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
use DreamFactory\Rave\Contracts\ServiceResponseInterface;

class ServiceResponse implements ServiceResponseInterface
{
    /**
     * @var int Http Status code
     */
    protected $statusCode = null;

    /**
     * @var mixed Response content
     */
    protected $content = null;

    /**
     * @var int ContentType
     */
    protected $contentType = null;

    /**
     * @param mixed $content     Response content
     * @param int   $contentType ContentType
     * @param int   $statusCode  Http Status code
     */
    public function __construct( $content = null, $contentType = null, $statusCode = null )
    {
        $this->content = $content;
        $this->contentType = $contentType;
        $this->statusCode = $statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatusCode( $code )
    {
        $this->statusCode = $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * {@inheritdoc}
     */
    public function setContent( $content )
    {
        $this->content = $content;
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
    public function setContentType( $type )
    {
        $this->contentType = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @return array All attributes as an array
     */
    public function toArray()
    {
        return [
            'status_code'  => $this->getStatusCode(),
            'content_type' => $this->getContentType(),
            'content'      => $this->getContent(),
        ];
    }

    /**
     * @param array $data Merge some attributes from an array
     */
    public function mergeFromArray( array $data )
    {
        $this->setStatusCode(ArrayUtils::get($data, 'status_code'));
        if (ArrayUtils::getBool( $data, 'payload_changed' ))
        {
            $this->setContentType(ArrayUtils::get($data, 'content_type'));
            $this->setContent(ArrayUtils::get($data, 'content'));
        }
    }
}