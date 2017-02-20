<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Library\Utility\Scalar;
use Illuminate\Support\Str;
use Request;

/**
 * Class ServiceRequest
 *
 * @package DreamFactory\Core\Utility
 */
class ServiceRequest implements ServiceRequestInterface
{
    use InternalServiceRequest;

    /**
     * {@inheritdoc}
     */
    public function getRequestorType()
    {
        return ServiceRequestorTypes::API;
    }

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
        return Scalar::boolval($this->getParameter($key, $default));
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return array
     * @throws BadRequestException
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
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
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
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
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

        return Request::getContentType();
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
            function ($value) {
                return (is_array($value)) ? implode(',', $value) : $value;
            },
            Request::header()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getFile($key = null, $default = null)
    {
        //Todo:Experiment Request::file()...
        if (null === $key) {
            return $_FILES;
        } else {
            return array_get($_FILES, $key, $default);
        }
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