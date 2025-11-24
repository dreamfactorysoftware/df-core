<?php namespace DreamFactory\Core\Utility;

use DreamFactory\Core\Enums\Verbs;

/**
 * Curl
 * A kick-ass cURL wrapper
 */
class Curl extends Verbs
{
    //*************************************************************************
    //* Members
    //*************************************************************************

    /**
     * @var string
     */
    protected static $_userName = null;
    /**
     * @var string
     */
    protected static $_password = null;
    /**
     * @var int
     */
    protected static $_hostPort = null;
    /**
     * @var array The error of the last call
     */
    protected static $_error = null;
    /**
     * @var array The results of the last call
     */
    protected static $_info = null;
    /**
     * @var array Default cURL options
     */
    protected static $_curlOptions = [];
    /**
     * @var int The last http code
     */
    protected static $_lastHttpCode = null;
    /**
     * @var array The last response headers
     */
    protected static $_lastResponseHeaders = null;
    /**
     * @var string
     */
    protected static $_responseHeaders = null;
    /**
     * @var int
     */
    protected static $_responseHeadersSize = null;
    /**
     * @var bool Enable/disable logging
     */
    protected static $_debug = true;
    /**
     * @var bool If true, and response is "application/json" content-type, it will be returned decoded
     */
    protected static $_autoDecodeJson = true;
    /**
     * @var bool If true, auto-decoded response is returned as an array
     */
    protected static $_decodeToArray = false;
    /**
     * @var bool If true, PUTs are transferred via CURL's INFILE method. Otherwise, data is PUT via POSTFIELDS.
     */
    public static $putAsFile = false;

    //*************************************************************************
    //* Methods
    //*************************************************************************

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return string|\stdClass
     */
    public static function get($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::GET, $payload, $curlOptions);
    }

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return bool|mixed|\stdClass
     */
    public static function put($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::PUT, $payload, $curlOptions);
    }

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return bool|mixed|\stdClass
     */
    public static function post($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::POST, $payload, $curlOptions);
    }

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return bool|mixed|\stdClass
     */
    public static function delete($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::DELETE, $payload, $curlOptions);
    }

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return bool|mixed|\stdClass
     */
    public static function head($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::HEAD, $payload, $curlOptions);
    }

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return bool|mixed|\stdClass
     */
    public static function options($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::OPTIONS, $payload, $curlOptions);
    }

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return bool|mixed|\stdClass
     */
    public static function copy($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::COPY, $payload, $curlOptions);
    }

    /**
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return bool|mixed|\stdClass
     */
    public static function patch($url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, static::PATCH, $payload, $curlOptions);
    }

    /**
     * @param string          $method
     * @param string          $url
     * @param array|\stdClass $payload
     * @param array           $curlOptions
     *
     * @return string|\stdClass
     */
    public static function request($method, $url, $payload = [], $curlOptions = [])
    {
        return static::_httpRequest($url, $method, $payload, $curlOptions);
    }

    /**
     * @param string $method
     * @param string $url
     * @param array  $payload
     * @param array  $curlOptions
     *
     * @throws \InvalidArgumentException
     * @return bool|mixed|\stdClass
     */
    protected static function _httpRequest($url, $method = self::GET, $payload = [], $curlOptions = [])
    {
        if (!static::contains($method)) {
            throw new \InvalidArgumentException('Invalid method "' . $method . '" specified.');
        }
        //	Reset!
        static::$_lastResponseHeaders = static::$_lastHttpCode = static::$_error = static::$_info = $_tmpFile = null;

        //	Build a curl request...
        $_curl = curl_init($url);

        //	Default CURL options for this method
        $_curlOptions = [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLINFO_HEADER_OUT    => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ];

        //	Merge in the global options if any
        if (!empty(static::$_curlOptions)) {
            $curlOptions = array_merge($curlOptions,
                static::$_curlOptions);
        }

        //	Add/override user options
        if (!empty($curlOptions)) {
            foreach ($curlOptions as $_key => $_value) {
                $_curlOptions[$_key] = $_value;
            }
        }

        if (null !== static::$_userName || null !== static::$_password) {
            $_curlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            $_curlOptions[CURLOPT_USERPWD] = static::$_userName . ':' . static::$_password;
        }

        //  Set verb-specific CURL options
        switch ($method) {
            case static::PUT:
                if (static::$putAsFile) {
                    $payload = json_encode(!empty($payload) ? $payload : []);
                    $_tmpFile = tmpfile();
                    fwrite($_tmpFile, $payload);
                    rewind($_tmpFile);

                    $_curlOptions[CURLOPT_PUT] = true;
                    $_curlOptions[CURLOPT_INFILE] = $_tmpFile;
                    $_curlOptions[CURLOPT_INFILESIZE] = mb_strlen($payload);
                } else {
                    $_curlOptions[CURLOPT_CUSTOMREQUEST] = static::PUT;
                    $_curlOptions[CURLOPT_POSTFIELDS] = $payload;
                }
                break;

            case static::POST:
                $_curlOptions[CURLOPT_POST] = true;
                $_curlOptions[CURLOPT_POSTFIELDS] = $payload;
                break;

            case static::HEAD:
                $_curlOptions[CURLOPT_NOBODY] = true;
                break;

            /** Patch, Merge, and Delete have payloads */
            case static::PATCH:
            case static::DELETE:
                $_curlOptions[CURLOPT_POSTFIELDS] = $payload;
                break;
        }

        //  Non-standard verbs need custom request option set...
        if (!in_array($method, [static::GET, static::POST, static::HEAD, static::PUT])) {
            $_curlOptions[CURLOPT_CUSTOMREQUEST] = $method;
        }

        if (null !== static::$_hostPort && !isset($_curlOptions[CURLOPT_PORT])) {
            $_curlOptions[CURLOPT_PORT] = static::$_hostPort;
        }

        //	Set our collected options
        curl_setopt_array($_curl, $_curlOptions);

        //	Make the call!
        $_result = curl_exec($_curl);

        static::$_info = curl_getinfo($_curl);
        static::$_lastHttpCode = array_get(static::$_info, 'http_code');
        static::$_responseHeaders = curl_getinfo($_curl, CURLINFO_HEADER_OUT);
        static::$_responseHeadersSize = curl_getinfo($_curl, CURLINFO_HEADER_SIZE);

        if (false === $_result) {
            static::$_error = [
                'code'    => curl_errno($_curl),
                'message' => curl_error($_curl),
            ];
        } elseif (true === $_result) {
            //	Worked, but no data...
            $_result = null;
        } else {
            //      Split up the body and headers if requested
            if ($_curlOptions[CURLOPT_HEADER]) {
                if (false === strpos($_result, "\r\n\r\n") || empty(static::$_responseHeadersSize)) {
                    $_headers = $_result;
                    $_body = null;
                } else {
                    $_headers = substr($_result, 0, static::$_responseHeadersSize);
                    $_body = substr($_result, static::$_responseHeadersSize);
                }

                if ($_headers) {
                    static::$_lastResponseHeaders = [];
                    $_raw = explode("\r\n", $_headers);

                    if (!empty($_raw)) {
                        $_first = true;

                        foreach ($_raw as $_line) {
                            if (empty($_line)) {
                                continue; // fix for curl returning blank lines in header string
                            }
                            //	Skip the first line (HTTP/1.x response)
                            if ($_first || preg_match('/^HTTP\/[0-9\.]+ [0-9]+/', $_line)) {
                                $_first = false;
                                continue;
                            }

                            $_parts = explode(':', $_line, 2);

                            if (!empty($_parts)) {
                                static::$_lastResponseHeaders[trim($_parts[0])] =
                                    count($_parts) > 1 ? trim($_parts[1]) : null;
                            }
                        }
                    }
                }

                $_result = $_body;
            }

            //	Attempt to auto-decode inbound JSON
            if (!empty($_result) && false !== stripos(array_get(static::$_info, 'content_type'),
                    'application/json',
                    0)
            ) {
                try {
                    if (false !== ($_json = @json_decode($_result, static::$_decodeToArray))) {
                        $_result = $_json;
                    }
                } catch (\Exception $_ex) {
                    //	Ignored
                }
            }

            //	Don't confuse error with empty data...
            if (false === $_result) {
                $_result = null;
            }
        }

        @curl_close($_curl);

        //	Close temp file if any
        if (null !== $_tmpFile) {
            @fclose($_tmpFile);
        }

        return $_result;
    }

    /**
     * @return array
     */
    public static function getErrorAsString()
    {
        if (!empty(static::$_error)) {
            return static::$_error['message'] . ' (' . static::$_error['code'] . ')';
        }

        return null;
    }

    /**
     * @param array $error
     *
     * @return void
     */
    protected static function _setError($error)
    {
        static::$_error = $error;
    }

    /**
     * @return array
     */
    public static function getError()
    {
        return static::$_error;
    }

    /**
     * @param int $hostPort
     *
     * @return void
     */
    public static function setHostPort($hostPort)
    {
        static::$_hostPort = $hostPort;
    }

    /**
     * @return int
     */
    public static function getHostPort()
    {
        return static::$_hostPort;
    }

    /**
     * @param array $info
     *
     * @return void
     */
    protected static function _setInfo($info)
    {
        static::$_info = $info;
    }

    /**
     * @param string $key          Leaving this null will return the entire structure, otherwise just the value for the supplied key
     * @param mixed  $defaultValue The default value to return if the $key was not found
     *
     * @return array
     */
    public static function getInfo($key = null, $defaultValue = null)
    {
        return null === $key ? static::$_info : array_get(static::$_info, $key, $defaultValue);
    }

    /**
     * @param string $password
     *
     * @return void
     */
    public static function setPassword($password)
    {
        static::$_password = $password;
    }

    /**
     * @return string
     */
    public static function getPassword()
    {
        return static::$_password;
    }

    /**
     * @param string $userName
     *
     * @return void
     */
    public static function setUserName($userName)
    {
        static::$_userName = $userName;
    }

    /**
     * @return string
     */
    public static function getUserName()
    {
        return static::$_userName;
    }

    /**
     * @param array $curlOptions
     *
     * @return void
     */
    public static function setCurlOptions($curlOptions)
    {
        static::$_curlOptions = $curlOptions;
    }

    /**
     * @return array
     */
    public static function getCurlOptions()
    {
        return static::$_curlOptions;
    }

    /**
     * @param int $lastHttpCode
     */
    protected static function _setLastHttpCode($lastHttpCode)
    {
        static::$_lastHttpCode = $lastHttpCode;
    }

    /**
     * @return int
     */
    public static function getLastHttpCode()
    {
        return static::$_lastHttpCode;
    }

    /**
     * @param boolean $debug
     */
    public static function setDebug($debug)
    {
        static::$_debug = $debug;
    }

    /**
     * @return boolean
     */
    public static function getDebug()
    {
        return static::$_debug;
    }

    /**
     * @param boolean $autoDecodeJson
     */
    public static function setAutoDecodeJson($autoDecodeJson)
    {
        static::$_autoDecodeJson = $autoDecodeJson;
    }

    /**
     * @return boolean
     */
    public static function getAutoDecodeJson()
    {
        return static::$_autoDecodeJson;
    }

    /**
     * @param boolean $decodeToArray
     */
    public static function setDecodeToArray($decodeToArray)
    {
        static::$_decodeToArray = $decodeToArray;
    }

    /**
     * @return boolean
     */
    public static function getDecodeToArray()
    {
        return static::$_decodeToArray;
    }

    /**
     * @return array
     */
    public static function getLastResponseHeaders()
    {
        return static::$_lastResponseHeaders;
    }

    /**
     * Returns the validated URL that has been called to get here
     *
     * @param bool $includeQuery If true, query string is included
     * @param bool $includePath  If true, the uri path is included
     *
     * @return string
     */
    public static function currentUrl($includeQuery = true, $includePath = true)
    {
        //	Are we SSL? Check for load balancer protocol as well...
        $_port =
            intval(array_get($_SERVER, 'HTTP_X_FORWARDED_PORT', array_get($_SERVER, 'SERVER_PORT', 80)));
        $_protocol = array_get($_SERVER,
                'HTTP_X_FORWARDED_PROTO',
                'http' . (array_get_bool($_SERVER, 'HTTPS') ? 's' : null)) . '://';
        $_host =
            array_get($_SERVER, 'HTTP_X_FORWARDED_HOST', array_get($_SERVER, 'HTTP_HOST', gethostname()));
        $_parts = parse_url($_protocol . $_host . array_get($_SERVER, 'REQUEST_URI'));

        if ((empty($_port) || !is_numeric($_port)) && null !== ($_parsePort = array_get($_parts, 'port'))) {
            $_port = @intval($_parsePort);
        }

        if (null !== ($_query = array_get($_parts, 'query'))) {
            $_query = static::urlSeparator($_query) . http_build_query(explode('&', $_query));
        }

        if (false !== strpos($_host,
                ':') || ($_protocol == 'https://' && $_port == 443) || ($_protocol == 'http://' && $_port == 80)
        ) {
            $_port = null;
        } else {
            $_port = ':' . $_port;
        }

        if (false !== strpos($_host, ':')) {
            $_port = null;
        }

        $_currentUrl =
            $_protocol . $_host . $_port . (true === $includePath ? array_get($_parts, 'path')
                : null) . (true === $includeQuery ? $_query : null);

        return $_currentUrl;
    }

    /**
     * Builds an URL, properly appending the payload as the query string.
     *
     * @param string $url           The target URL
     * @param array  $payload       The query string data. May be an array or object containing properties. The array form may be a simple
     *                              one-dimensional structure, or an array of arrays (who in turn may contain other arrays).
     * @param string $numericPrefix If numeric indices are used in the base array and this parameter is provided, it will be prepended to the numeric
     *                              index for elements in the base array only. This is meant to allow for legal variable names when the data is
     *                              decoded by PHP or another CGI application later on.
     * @param string $argSeparator  Character to use to separate arguments. Defaults to '&'
     * @param int    $encodingType  If encodingType is PHP_QUERY_RFC1738 (the default), then encoding is as application/x-www-form-urlencoded, spaces
     *                              will be encoded with plus (+) signs If encodingType is PHP_QUERY_RFC3986, spaces will be encoded with %20
     *
     * @return string an URL-encoded string
     */
    public static function buildUrl($url, $payload = [], $numericPrefix = null, $argSeparator = '&', $encodingType = PHP_QUERY_RFC1738)
    {
        $_query = \http_build_query($payload, $numericPrefix, $argSeparator, $encodingType);

        return $url . static::urlSeparator($url, $argSeparator) . $_query;
    }

    /**
     * Returns the proper separator for an addition to the URL (? or &)
     *
     * @param string $url          The URL to test
     * @param string $argSeparator Defaults to '&' but you can override
     *
     * @return string
     */
    public static function urlSeparator($url, $argSeparator = '&')
    {
        return (false === strpos($url, '?', 0) ? '?' : $argSeparator);
    }
}
