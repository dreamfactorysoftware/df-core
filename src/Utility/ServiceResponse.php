<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Contracts\ServiceResponseInterface;

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
     * @var array Array of additional headers
     */
    protected $headers = [];

    /**
     * @param mixed       $content      Response content
     * @param string|null $content_type Content Type of content
     * @param int         $status       HTTP Status code
     * @param array|null  $headers      HTTP headers
     */
    public function __construct($content = null, $content_type = null, $status = self::HTTP_OK, $headers = [])
    {
        $this->content = $content;
        $this->contentType = $content_type;
        if (!empty($content_type)) {
            $this->dataFormat = DataFormats::fromMimeType($content_type);
        } elseif (is_array($content)) {
            $this->dataFormat = DataFormats::PHP_ARRAY;
        } elseif (is_object($content)) {
            $this->dataFormat = DataFormats::PHP_OBJECT;
        } else {
            $this->dataFormat = DataFormats::RAW;
        }
        $this->statusCode = $status;
        $this->headers = (array)$headers;
    }

    /**
     * {@inheritdoc}
     */
    public function setStatusCode($code)
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
    public function setContent($content)
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
    public function setContentType($type)
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
    public function setDataFormat($format)
    {
        $this->dataFormat = $format;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataFormat()
    {
        return $this->dataFormat;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        return $this->headers;
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
            'format'       => $this->getDataFormat(),
            'headers'      => $this->getHeaders(),
        ];
    }

    /**
     * @param array $data Merge some attributes from an array
     */
    public function mergeFromArray(array $data)
    {
        if (array_key_exists('status_code', $data)) {
            $this->setStatusCode(array_get($data, 'status_code'));
        }
        if (array_key_exists('content', $data)) {
            $this->setContent(array_get($data, 'content'));
            $this->setContentType(array_get($data, 'content_type'));
            $this->setDataFormat(array_get($data, 'format'));
        }
        if (array_key_exists('headers', $data)) {
            $this->setHeaders(array_merge($this->headers, array_get($data, 'headers', [])));
        }
    }
}