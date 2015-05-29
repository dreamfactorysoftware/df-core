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
use DreamFactory\Rave\Enums\DataFormats;

class ServiceResponse implements ServiceResponseInterface
{
    /**
     * @var int HTTP Status code, see HttpStatusCodes
     */
    protected $statusCode = null;

    /**
     * @var mixed Response content/data
     */
    protected $content = null;

    /**
     * @var string Content Type header value for this content/data (i.e. MIME type).
     * If null, the response Content-Type is determined by the $dataFormat.
     */
    protected $contentType = null;

    /**
     * @var int Data format of the content, see DataFormats
     */
    protected $dataFormat = null;

    /**
     * @param mixed  $content      Response content
     * @param int    $format       Data Format of content
     * @param int    $status       HTTP Status code
     * @param string $content_type Content Type of content
     */
    public function __construct( $content = null, $format = DataFormats::PHP_ARRAY, $status = ServiceResponseInterface::HTTP_OK, $content_type = null )
    {
        $this->content = $content;
        $this->dataFormat = $format;
        $this->statusCode = $status;
        $this->contentType = $content_type;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatusCode( $code )
    {
        $this->statusCode = $code;

        return $this;
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

        return $this;
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

        return $this;
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
    public function setContentFormat( $format )
    {
        $this->dataFormat = $format;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getContentFormat()
    {
        return $this->dataFormat;
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
        $this->setStatusCode( ArrayUtils::get( $data, 'status_code' ) );
        if ( ArrayUtils::getBool( $data, 'payload_changed' ) )
        {
            $this->setContentType( ArrayUtils::get( $data, 'content_type' ) );
            $this->setContent( ArrayUtils::get( $data, 'content' ) );
        }
    }
}