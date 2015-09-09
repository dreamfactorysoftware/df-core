<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Library\Utility\Scalar;
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
        if (!is_null($this->parameters)) {
            if (null === $key) {
                return $this->parameters;
            } else {
                return ArrayUtils::get($this->parameters, $key, $default);
            }
        }

        if (null === $key) {
            return Request::query();
        } else {
            return Request::query($key, $default);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        if (!is_null($this->parameters)) {
            return $this->parameters;
        }

        return Request::query();
    }

    public function getParameterAsBool($key, $default = false)
    {
        if (!is_null($this->parameters)) {
            return ArrayUtils::getBool($this->parameters, $key, $default);
        }

        return Scalar::boolval(Request::query($key, $default));
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
                return ArrayUtils::get($this->contentAsArray, $key, $default);
            }
        }

        //This just checks the Request Header Content-Type.
        if (Request::isJson()) {
            //Decoded json data is cached internally using parameterBag.
            return $this->json($key, $default);
        }

        //Check the actual content. If it is blank return blank array.
        $content = $this->getContent();
        if (empty($content)) {
            return [];
        }

        //Checking this last to be more efficient.
        if (json_decode($content) !== null) {
            //Decoded json data is cached internally using parameterBag.
            return $this->json($key, $default);
        }

        //Todo:Check for additional content-type here.

        throw new BadRequestException('Unrecognized payload type');
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
        if (!is_null($this->headers)) {
            if (null === $key) {
                return $this->headers;
            } else {
                return ArrayUtils::get($this->headers, $key, $default);
            }
        }

        if (null === $key) {
            return Request::header();
        } else {
            return Request::header($key, $default);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        if (!is_null($this->headers)) {
            return $this->headers;
        }

        return Request::header();
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
            return ArrayUtils::get($_FILES, $key, $default);
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