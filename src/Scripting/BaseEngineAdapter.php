<?php
namespace DreamFactory\Core\Scripting;

use DreamFactory\Core\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Curl;
use DreamFactory\Library\Utility\Enums\Verbs;
use \Log;

/**
 * Allows platform access to a scripting engine
 */
abstract class BaseEngineAdapter
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int The default cache ttl, 5m = 300s
     */
    const DEFAULT_CACHE_TTL = 300;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array A list of paths where our scripts might live
     */
    protected static $libraryPaths;
    /**
     * @var array The list of registered/known libraries
     */
    protected static $libraries = [];
    /**
     * @var ScriptingEngineInterface The engine
     */
    protected $engine;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     *
     * @throws ServiceUnavailableException
     */
    public function __construct(array $settings = [])
    {
        //  Save off the engine
        $this->engine = array_get($settings, 'engine', $this->engine);
    }

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return mixed
     */
    public static function startup($options = null)
    {
        static::initializeLibraryPaths(array_get($options, 'library_paths', []));
    }

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return mixed
     */
    public static function shutdown()
    {
        \Cache::add('scripting.library_paths', static::$libraryPaths, static::DEFAULT_CACHE_TTL);
        \Cache::add('scripting.libraries', static::$libraries, static::DEFAULT_CACHE_TTL);
    }

    /**
     * Look through the known paths for a particular script. Returns full path to script file.
     *
     * @param string $name           The name/id of the script
     * @param string $path           The name of the script
     * @param bool   $returnContents If true, the contents of the file, if found, are returned. Otherwise, the only the
     *                               path is returned
     *
     * @return string
     */
    public static function loadScript($name, $path = null, $returnContents = true)
    {
        if ($path) {
            // no longer support file paths for scripts?
        }
        //  Already read, return script
        if (null !== ($script = array_get(static::$libraries, $name))) {
            return $returnContents ? file_get_contents($script) : $script;
        }

        $script = ltrim($script, ' /');

        //  Spin through paths and look for the script
        foreach (static::$libraryPaths as $path) {
            $check = $path . '/' . $script;

            if (is_file($check) && is_readable($check)) {
                array_set(static::$libraries, $name, $check);

                return $returnContents ? file_get_contents($check) : $check;
            }
        }

        return false;
    }

    /**
     * @param array $libraryPaths
     *
     * @throws ServiceUnavailableException
     */
    protected static function initializeLibraryPaths($libraryPaths = null)
    {
        static::$libraryPaths = \Cache::get('scripting.library_paths', []);
        static::$libraries = \Cache::get('scripting.libraries', []);

        //  Add ones from constructor
        $libraryPaths = (is_array($libraryPaths) ? $libraryPaths : []);

        //  Application storage script path
        $libraryPaths[] = storage_path('scripting');

        //  Merge in config libraries...
        $configPaths = \Config::get('df.scripting.paths', []);
        $configPaths = (is_array($configPaths) ? $configPaths : []);
        $libraryPaths = array_merge($libraryPaths, $configPaths);

        //  Add them to collection if valid
        if (is_array($libraryPaths)) {
            foreach ($libraryPaths as $path) {
                if (!in_array($path, static::$libraryPaths)) {
                    if (!empty($path) || is_dir($path) || is_readable($path)) {
                        static::$libraryPaths[] = $path;
                    } else {
                        Log::debug("Invalid scripting library path given $path.");
                    }
                }
            }
        }

        \Cache::add('scripting.library_paths', static::$libraryPaths, static::DEFAULT_CACHE_TTL);

        if (empty(static::$libraryPaths)) {
            Log::debug('No scripting library paths found.');
        }
    }

    /**
     * @return array
     */
    public static function getLibraries()
    {
        return static::$libraries;
    }

    /**
     * @return array
     */
    public static function getLibraryPaths()
    {
        return static::$libraryPaths;
    }

    /**
     * @param string $libraryPath An absolute path to a script library
     */
    public static function addLibraryPath($libraryPath)
    {
        if (!is_dir($libraryPath) || !is_readable($libraryPath)) {
            throw new \InvalidArgumentException('The path "' . $libraryPath . '" is invalid.');
        }

        if (!in_array($libraryPath, static::$libraryPaths)) {
            static::$libraryPaths[] = $libraryPath;
        }
    }

    /**
     * @param string $name   The name/id of this script
     * @param string $script The file for this script
     */
    public static function addLibrary($name, $script)
    {
        if (false === ($path = static::loadScript($name, $script, false))) {
            throw new \InvalidArgumentException('The script "' . $script . '" was not found.');
        }
    }

    /**
     * Locates and loads a library returning the contents
     *
     * @param string $id   The id of the library (i.e. "lodash", "underscore", etc.)
     * @param string $file The relative path/name of the library file
     *
     * @return string
     */
    protected static function getLibrary($id, $file = null)
    {
        if (null !== $file || array_key_exists($id, static::$libraries)) {
            $file = $file ?: static::$libraries[$id];

            //  Find the library
            foreach (static::$libraryPaths as $name => $path) {
                $filePath = $path . DIRECTORY_SEPARATOR . $file;

                if (file_exists($filePath) && is_readable($filePath)) {
                    return file_get_contents($filePath, 'r');
                }
            }
        }

        throw new \InvalidArgumentException('The library id "' . $id . '" could not be located.');
    }

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
    protected static function externalRequest($method, $url, $payload = [], $curlOptions = [])
    {
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

        $result = Curl::request($method, $url, $payload, $curlOptions);
        $contentType = Curl::getInfo('content_type');
        $status = Curl::getLastHttpCode();
        if ($status >= 300) {
            if (!is_string($result)) {
                $result = json_encode($result);
            }

            throw new RestException($status, $result, $status);
        }

        return ResponseFactory::create($result, $contentType, $status);
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
                                $name = array_get($tmp, 0, $k);
                                $value = array_get($tmp, 1);
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
    protected static function getExposedApi()
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

        $api->post = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::POST, $path, $payload, $curlOptions);
        };

        $api->put = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::PUT, $path, $payload, $curlOptions);
        };

        $api->patch = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::PATCH, $path, $payload, $curlOptions);
        };

        $api->delete = function ($path, $payload = null, $curlOptions = []){
            return static::inlineRequest(Verbs::DELETE, $path, $payload, $curlOptions);
        };

        return $api;
    }

    public static function buildPlatformAccess($identifier)
    {
        return [
            'api'     => static::getExposedApi(),
            'config'  => \Config::all(),
            'session' => Session::all(),
            'store'   => new ScriptSession(\Config::get("script.$identifier.store"), app('cache'))
        ];
    }
}