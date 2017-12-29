<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Exceptions\BadRequestException;
use Illuminate\Support\Str;
use Request;

/**
 * Class ServiceRequest
 *
 * @package DreamFactory\Core\Utility
 */
class ServiceRequest extends InternalServiceRequest implements ServiceRequestInterface
{
    /**
     * {@inheritdoc}
     */
    public function getMethod()
    {
        if (!empty($this->method)) {
            return $this->method;
        }

        return Request::getMethod();
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestUri()
    {
        if (!empty($this->requestUri)) {
            return $this->requestUri;
        }

        return Request::getRequestUri();
    }

    /**
     * {@inheritdoc}
     */
    public function getService()
    {
        if (!empty($this->service)) {
            return $this->service;
        }

        $service = '';
        $uri = trim($this->getRequestUri(), '/');
        if (!empty($uri)) {
            $uriParts = explode('/', $uri);
            // Need to get the 3rd element of the array as
            // a URI looks like api/v2/<service>/...
            $service = array_get($uriParts, 2);
        }

        return $service;
    }

    /**
     * {@inheritdoc}
     */
    public function getResource()
    {
        if (!empty($this->service)) {
            return $this->service;
        }

        $resource = '';
        $uri = trim($this->getRequestUri(), '/');
        if (!empty($uri)) {
            $uriParts = explode('/', $uri);
            // Need to get all elements after the 3rd of the array as
            // a URI looks like api/v2/<service>/<resource>/<resource>...
            array_shift($uriParts);
            array_shift($uriParts);
            array_shift($uriParts);
            $resource = implode('/', $uriParts);
        }

        return $resource;
    }

    /**
     * {@inheritdoc}
     */
    public function getParameter($key = null, $default = null)
    {
        if ($this->parameters) {
            if (null === $key) {
                return $this->parameters;
            } else {
                return array_get($this->parameters, $key, $default);
            }
        }

        return Request::query($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        if ($this->parameters) {
            return $this->parameters;
        }

        return Request::query();
    }

    public function getParameterAsBool($key, $default = false)
    {
        return to_bool($this->getParameter($key, $default));
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return array
     * @throws BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     */
    public function getPayloadData($key = null, $default = null)
    {
        if (!empty($this->contentAsArray)) {
            if (null === $key) {
                return $this->contentAsArray;
            } else {
                return array_get($this->contentAsArray, $key, $default);
            }
        }

        //This just checks the Request Header Content-Type.
        if (Request::isJson()) {
            // Decoded json data is cached internally using parameterBag.
            return $this->json($key, $default);
        }

        if (Str::contains(Request::header('CONTENT_TYPE'), 'x-www-form-urlencoded')) {
            // Formatted xml data is stored in $this->contentAsArray
            return $this->input($key, $default);
        }

        if (Str::contains(Request::header('CONTENT_TYPE'), 'xml')) {
            // Formatted xml data is stored in $this->contentAsArray
            return $this->xml($key, $default);
        }

        if (Str::contains(Request::header('CONTENT_TYPE'), 'csv')) {
            // Formatted csv data is stored in $this->contentAsArray
            return $this->csv($key, $default);
        }

        if (Str::contains(Request::header('CONTENT_TYPE'), 'text/plain')) {
            // Plain text content, return as is.
            return $this->getContent();
        }

        //Check the actual content. If it is blank return blank array.
        $content = $this->getContent();
        if (empty($content)) {
            if (null === $key) {
                return [];
            } else {
                return $default;
            }
        }

        //Checking this last to be more efficient.
        if (json_decode($content) !== null) {
            $this->contentType = DataFormats::toMimeType(DataFormats::JSON);

            //Decoded json data is cached internally using parameterBag.
            return $this->json($key, $default);
        } else {
            if (DataFormatter::xmlToArray($content) !== null) {
                $this->contentType = DataFormats::toMimeType(DataFormats::XML);

                return $this->xml($key, $default);
            } else {
                if (!empty(DataFormatter::csvToArray($content))) {
                    $this->contentType = DataFormats::toMimeType(DataFormats::CSV);

                    return $this->csv($key, $default);
                }
            }
        }

        throw new BadRequestException('Unrecognized payload type');
    }

    /**
     * Returns CSV payload data
     *
     * @param null $key
     * @param null $default
     *
     * @return array|mixed|null
     */
    protected function csv($key = null, $default = null)
    {
        if (empty($this->contentAsArray)) {
            $content = $this->getContent();
            $data = DataFormatter::csvToArray($content);

            if (!empty($data)) {
                $this->contentAsArray = ResourcesWrapper::wrapResources($data);
            }
        }

        if (null === $key) {
            return $this->contentAsArray;
        } else {
            return array_get($this->contentAsArray, $key, $default);
        }
    }

    /**
     * Returns XML payload data.
     *
     * @param null $key
     * @param null $default
     *
     * @return array|mixed|null
     */
    protected function xml($key = null, $default = null)
    {
        if (empty($this->contentAsArray)) {
            $content = $this->getContent();
            $data = DataFormatter::xmlToArray($content);

            if (!empty($data)) {
                if ((1 === count($data)) && !array_key_exists(ResourcesWrapper::getWrapper(), $data)) {
                    // All XML comes wrapped in a single wrapper, if not resource wrapper, remove it
                    $data = reset($data);
                }

                $this->contentAsArray = $data;
            }
        }

        if (null === $key) {
            return $this->contentAsArray;
        } else {
            return array_get($this->contentAsArray, $key, $default);
        }
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return mixed
     */
    protected function json($key = null, $default = null)
    {
        if (null === $key) {
            return Request::json()->all();
        } else {
            return Request::json($key, $default);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getContent()
    {
        if (!empty($this->content)) {
            return $this->content;
        }

        return Request::getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function getContentType()
    {
        if (!empty($this->contentType)) {
            return $this->contentType;
        }

        return $this->getHeader('content-type', 'application/json');
    }

    /**
     * {@inheritdoc}
     */
    public function getHeader($key = null, $default = null)
    {
        if (null === $key) {
            return $this->getHeaders();
        }

        if ($this->headers) {
            return array_get($this->headers, $key, $default);
        }

        return Request::header($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        if ($this->headers) {
            return $this->headers;
        }

        return array_map(
            function ($value){
                return (is_array($value)) ? implode(',', $value) : $value;
            },
            Request::header()
        );
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return array|mixed
     */
    public function getFile($key = null, $default = null)
    {
        if (null === $key) {
            $files = [];
            foreach ($_FILES as $key => $FILE){
                $files[$key] = $this->formatFileInfo($FILE);
            }
            return $files;
        } else {
            $file = array_get($_FILES, $key, $default);
            $file = $this->formatFileInfo($file);
            return $file;
        }
    }

    /**
     * Format file data for ease of use
     *
     * @param array|null $fileInfo
     *
     * @return array
     */
    protected function formatFileInfo($fileInfo)
    {
        if(empty($fileInfo) || !is_array($fileInfo) || isset($fileInfo[0])){
            return $fileInfo;
        }

        $file = [];
        foreach ($fileInfo as $key => $value){
            if(is_array($value)){
                foreach ($value as $k => $v){
                    $file[$k][$key] = $v;
                }
            } else {
                $file[$key] = $value;
            }
        }

        return $file;
    }

    /**
     * {@inheritdoc}
     */
    public function getDriver()
    {
        return Request::instance();
    }

    /**
     * {@inheritdoc}
     */
    public function getApiKey()
    {
        if (!empty($this->parameters) && !empty($this->headers)) {
            $apiKey = $this->getParameter('api_key');
            if (empty($apiKey)) {
                $apiKey = $this->getHeader('X_DREAMFACTORY_API_KEY');
            }
        } else {
            $apiKey = Request::input('api_key');
            if (empty($apiKey)) {
                $apiKey = Request::header('X_DREAMFACTORY_API_KEY');
            }
        }

        if (empty($apiKey)) {
            $apiKey = null;
        }

        return $apiKey;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($key, $value)
    {
        $this->loadParameters();
        $this->parameters[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeader($key, $data)
    {
        $this->loadHeaders();
        $this->headers[$key] = $data;
    }

    /**
     * Returns request input.
     *
     * @param null $key
     * @param null $default
     *
     * @return array|string
     */
    public function input($key = null, $default = null)
    {
        return Request::input($key, $default);
    }

    /**
     * Loads parameters from laravel request object.
     */
    protected function loadParameters()
    {
        if (is_null($this->parameters)) {
            $this->parameters = Request::query();
        }
    }

    /**
     * Loads headers from laravel request object.
     */
    protected function loadHeaders()
    {
        if (is_null($this->headers)) {
            $this->headers = Request::header();
        }
    }
}