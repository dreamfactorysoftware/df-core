<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Scripting\ScriptServiceRequest;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use \Log;

/**
 * Allows platform access to a internal API
 */
class ExposedApi
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    //*************************************************************************
    //	Members
    //*************************************************************************

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $method
     * @param string $url
     * @param mixed  $payload
     * @param array  $curlOptions
     *
     * @return \DreamFactory\Core\Utility\ServiceResponse
     * @throws \DreamFactory\Core\Exceptions\NotImplementedException
     * @throws \DreamFactory\Core\Exceptions\RestException
     */
    public static function externalRequest($method, $url, $payload = [], $curlOptions = [])
    {
        $result = Curl::request($method, $url, $payload, $curlOptions);
        $contentType = Curl::getInfo('content_type');
        $format = DataFormats::fromMimeType($contentType);
        $status = Curl::getLastHttpCode();
        if ($status >= 300) {
            if (!is_string($result)) {
                $result = json_encode($result);
            }

            throw new RestException($status, $result, $status);
        }

        return ResponseFactory::create($result, $format, $status, $contentType);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array  $payload
     * @param array  $curlOptions Additional CURL options for external requests
     *
     * @return array
     */
    public static function inlineRequest($method, $path, $payload = null, $curlOptions = [])
    {
        if (null === $payload || 'null' == $payload) {
            $payload = [];
        }

        if (!empty($curlOptions)) {
            $options = [];

            foreach ($curlOptions as $key => $value) {
                if (!is_numeric($key)) {
                    if (defined($key)) {
                        $options[constant($key)] = $value;
                    }
                }
            }

            $curlOptions = $options;
            unset($options);
        }

        try {
            if ('https:/' == ($protocol = substr($path, 0, 7)) || 'http://' == $protocol) {
                $result = static::externalRequest($method, $path, $payload, $curlOptions);
            } else {
                $result = null;
                $params = [];
                if (false !== $pos = strpos($path, '?')) {
                    $paramString = substr($path, $pos + 1);
                    if (!empty($paramString)) {
                        $pArray = explode('&', $paramString);
                        foreach ($pArray as $k => $p) {
                            if (!empty($p)) {
                                $tmp = explode('=', $p);
                                $name = ArrayUtils::get($tmp, 0, $k);
                                $value = ArrayUtils::get($tmp, 1);
                                $params[$name] = urldecode($value);
                            }
                        }
                    }
                    $path = substr($path, 0, $pos);
                }

                if (false === ($pos = strpos($path, '/'))) {
                    $serviceName = $path;
                    $resource = null;
                } else {
                    $serviceName = substr($path, 0, $pos);
                    $resource = substr($path, $pos + 1);

                    //	Fix removal of trailing slashes from resource
                    if (!empty($resource)) {
                        if ((false === strpos($path, '?') && '/' === substr($path, strlen($path) - 1, 1)) ||
                            ('/' === substr($path, strpos($path, '?') - 1, 1))
                        ) {
                            $resource .= '/';
                        }
                    }
                }

                if (empty($serviceName)) {
                    return null;
                }

                $format = DataFormats::PHP_ARRAY;
                if (!is_array($payload)) {
                    $format = DataFormats::TEXT;
                }

                Session::checkServicePermission($method, $serviceName, $resource, ServiceRequestorTypes::SCRIPT);

                $request = new ScriptServiceRequest($method, $params);
                $request->setContent($payload, $format);

                //  Now set the request object and go...
                $service = ServiceHandler::getService($serviceName);
                $result = $service->handleRequest($request, $resource);
            }
        } catch (\Exception $ex) {
            $result = ResponseFactory::create($ex);

            Log::error('Exception: ' . $ex->getMessage(), ['response' => $result]);
        }

        return ResponseFactory::sendScriptResponse($result);
    }

    /**
     * @return \stdClass
     */
    public static function getExposedApi()
    {
        static $api;

        if (null !== $api) {
            return $api;
        }

        $api = new \stdClass();

        $api->call = function ($method, $path, $payload = null, $curlOptions = []){
            return static::inlineRequest($method, $path, $payload, $curlOptions);
        };

        $api->get = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::GET, $path, $payload, $curlOptions);
        };

        $api->put = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::PUT, $path, $payload, $curlOptions);
        };

        $api->post = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::POST, $path, $payload, $curlOptions);
        };

        $api->delete = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::DELETE, $path, $payload, $curlOptions);
        };

        $api->patch = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::PATCH, $path, $payload, $curlOptions);
        };

        return $api;
    }
}