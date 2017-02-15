<?php
namespace DreamFactory\Core\Contracts;

interface ServiceResponseInterface extends HttpStatusCodeInterface
{
    /**
     * @param $code int Http Status code
     *
     * @return ServiceResponseInterface
     */
    public function setStatusCode($code);

    /**
     * @return int Http Status code
     */
    public function getStatusCode();

    /**
     * @param $content mixed Response content
     *
     * @return ServiceResponseInterface
     */
    public function setContent($content);

    /**
     * @return mixed Response content
     */
    public function getContent();

    /**
     * @param $type string Content Type (i.e. MIME type)
     *
     * @return ServiceResponseInterface
     */
    public function setContentType($type);

    /**
     * @return null|string Content Type (i.e. MIME type) or null if not set
     */
    public function getContentType();

    /**
     * @param $format int DataFormats
     *
     * @return ServiceResponseInterface
     */
    public function setDataFormat($format);

    /**
     * @return int DataFormats
     */
    public function getDataFormat();

    /**
     * @param $headers array Additional headers to send
     *
     * @return ServiceResponseInterface
     */
    public function setHeaders($headers);

    /**
     * @return array Additional headers
     */
    public function getHeaders();

    /**
     * @return array All attributes as an array
     */
    public function toArray();

    /**
     * @param array $data Merge some attributes from an array
     */
    public function mergeFromArray(array $data);
}