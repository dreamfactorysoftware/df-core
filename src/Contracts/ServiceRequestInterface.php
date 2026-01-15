<?php

namespace DreamFactory\Core\Contracts;

use DreamFactory\Core\Enums\DataFormats;
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
    public function setMethod($method);

    /**
     * @return string
     */
    public function getRequestUri();

    /**
     * @param string $service
     */
    public function setService($service);

    /**
     * @return string
     */
    public function getService();

    /**
     * @param string $resource
     */
    public function setResource($resource);

    /**
     * @return string
     */
    public function getResource();

    /**
     * @param string $uri
     *
     * @return mixed
     */
    public function setRequestUri($uri);

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
    public function getParameter($key = null, $default = null);

    /**
     * @param mixed $key
     * @param bool  $default
     *
     * @return boolean
     */
    public function getParameterAsBool($key, $default = false);

    /**
     * @param array $parameters
     */
    public function setParameters(array $parameters);

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function setParameter($key, $value);

    /**
     * @param null|string $key
     * @param null|string $default
     *
     * @return array
     */
    public function getPayloadData($key = null, $default = null);

    /**
     * @param array $data
     */
    public function setPayloadData(array $data);

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function setPayloadKeyValue($key, $value);

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
    public function setContent($content, $type = DataFormats::PHP_ARRAY);

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
    public function getHeader($key = null, $default = null);

    /**
     * @param array $headers
     */
    public function setHeaders(array $headers);

    /**
     * @param mixed $key
     * @param mixed $value
     */
    public function setHeader($key, $value);

    /**
     * Retrieve a file from the request.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return UploadedFile|array
     */
    public function getFile($key = null, $default = null);

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
    public function mergeFromArray(array $data);

    /**
     * Returns the underlying Request object if any that handles the
     * HTTP requests.
     *
     * @return mixed
     */
    public function getDriver();

    /**
     * Returns the api_key for the request.
     *
     * @return mixed
     */
    public function getApiKey();

    /**
     * Returns request input
     *
     * @param null $key
     * @param null $default
     *
     * @return array|string
     */
    public function input($key = null, $default = null);
}