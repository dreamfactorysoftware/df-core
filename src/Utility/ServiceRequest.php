<?php

namespace DreamFactory\Core\Utility;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Library\Utility\Scalar;
use Request;
use DreamFactory\Core\Contracts\ServiceRequestInterface;

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
        $this->getParameters();

        if (null === $key) {
            return $this->parameters;
        } else {
            return ArrayUtils::get($this->parameters, $key, $default);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getParameters()
    {
        if(empty($this->parameters)){
            $this->parameters = Request::query();
        }

        return $this->parameters;
    }

    public function getParameterAsBool($key, $default = false)
    {
        $this->getParameters();

        return ArrayUtils::getBool($this->parameters, $key, $default);
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
        $this->getHeaders();

        if (null === $key) {
            return $this->headers;
        } else {
            return ArrayUtils::get($this->headers, $key, $default);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getHeaders()
    {
        if (empty($this->headers)) {
            $this->headers = Request::header();
        }

        return $this->headers;
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

        if(empty($apiKey))
        {
           $apiKey = null;
        }

        return $apiKey;
    }

    /**
     * @param array $parameters
     *
     * @return $this
     */
    public function setParameters(array $parameters)
    {
        $this->getParameters();
        foreach($parameters as $key => $value){
            $this->setParameter($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setParameter($key, $value)
    {
        $this->getParameters();
        $this->parameters[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeaders(array $headers)
    {
        $this->getHeaders();
        foreach($headers as $key => $value){
            $this->setHeader($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setHeader($key, $data)
    {
        $this->getHeaders();
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
    public function input($key=null, $default=null)
    {
        return Request::input($key, $default);
    }
}