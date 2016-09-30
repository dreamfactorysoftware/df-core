<?php
namespace DreamFactory\Core\Scripting\Engines;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Scripting\BaseEngineAdapter;
use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Core\Scripting\ScriptSession;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Scripting\ScriptServiceRequest;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Utility\ResponseFactory;
use Log;
use Config;
use ServiceManager;

/**
 * Plugin for the php-v8js extension which exposes the V8 Javascript engine
 */
class V8Js extends BaseEngineAdapter
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The name of the object which exposes PHP
     */
    const EXPOSED_OBJECT_NAME = 'DSP';
    /**
     * @type string The template for all module loading
     */
    const MODULE_LOADER_TEMPLATE = 'require("{module}");';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var bool True if system version of V8Js supports module loading
     */
    protected static $moduleLoaderAvailable = false;
    /**
     * @var \ReflectionClass
     */
    protected static $mirror;
    /**
     * @var \\V8Js
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
        parent::__construct($settings);

        if (!extension_loaded('v8js')) {
            throw new ServiceUnavailableException("This instance cannot run server-side javascript scripts. The 'v8js' is not available.");
        }

        $name = array_get($settings, 'name', self::EXPOSED_OBJECT_NAME);
        $variables = array_get($settings, 'variables', []);
        $extensions = array_get($settings, 'extensions', []);
        // accept comma-delimited string
        $extensions = (is_string($extensions)) ? array_map('trim', explode(',', trim($extensions, ','))) : $extensions;
        $reportUncaughtExceptions = Scalar::boolval(array_get($settings, 'report_uncaught_exceptions', false));
        $logMemoryUsage = Scalar::boolval(array_get($settings, 'log_memory_usage', false));

        static::startup($settings);

        //  Set up our script mappings for module loading
        /** @noinspection PhpUndefinedClassInspection */
        $this->engine = new \V8Js($name, $variables, $extensions, $reportUncaughtExceptions);

        /**
         * This is the callback for the exposed "require()" function in the sandbox
         */
        if (static::$moduleLoaderAvailable) {
            /** @noinspection PhpUndefinedMethodInspection */
            $this->engine->setModuleLoader(
                function ($module){
                    return static::loadScriptingModule($module);
                }
            );
        } else {
            /** @noinspection PhpUndefinedClassInspection */
            Log::debug('  * no "require()" support in V8 library v' . \V8Js::V8_VERSION);
        }

        if ($logMemoryUsage) {
            /** @noinspection PhpUndefinedMethodInspection */
            $loadedExtensions = $this->engine->getExtensions();

            Log::debug(
                '  * engine created with the following extensions: ' .
                (!empty($loadedExtensions) ? implode(', ', array_keys($loadedExtensions)) : '**NONE**')
            );
        }
    }

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return void
     */
    public static function startup($options = null)
    {
        parent::startup($options);

        //	Find out if we have support for "require()"
        $mirror = new \ReflectionClass('\\V8Js');

        /** @noinspection PhpUndefinedMethodInspection */
        if (false !== (static::$moduleLoaderAvailable = $mirror->hasMethod('setModuleLoader'))) {
        }

        //  Register any extensions
        if (null !== $extensions = array_get($options, 'extensions', [])) {
            // accept comma-delimited string
            $extensions =
                (is_string($extensions)) ? array_map('trim', explode(',', trim($extensions, ','))) : $extensions;
            static::registerExtensions($extensions);
        }
    }

    public static function buildPlatformAccess($identifier)
    {
        /**
         * For some mysterious reason the v8 library produces segmentation fault for PHP 7
         * when $session ($session is an array) is used directly below. However,
         * when $session is re-constructed into a $newSession variable using the
         * code below it magically works!
         */
        $session = Session::all();
        $newSession = [];
        foreach ($session as $key => $value){
            $newSession[$key] = $value;
        }

        return [
            'api'     => static::getExposedApi(),
            'config'  => Config::get('df'),
            'session' => $newSession,
            'store'   => new ScriptSession(Config::get("script.$identifier.store"), app('cache'))
        ];
    }

    /**
     * Process a single script
     *
     * @param string $script          The string to execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of data to be passed to this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeString($script, $identifier, array &$data = [], array $engineArguments = [])
    {
        $data['__tag__'] = 'exposed_event';

        try {
            $runnerShell = $this->enrobeScript($script, $data, static::buildPlatformAccess($identifier));

            /** @noinspection PhpUndefinedMethodInspection */
            /** @noinspection PhpUndefinedClassInspection */
            $result = $this->engine->executeString($runnerShell, $identifier, \V8Js::FLAG_FORCE_ARRAY);

            return $result;
        } /** @noinspection PhpUndefinedClassInspection */
        catch (\V8JsException $ex) {
            $message = $ex->getMessage();

            /**
             * @note     V8JsTimeLimitException was released in a later version of the libv8 library than is supported by the current PECL v8js extension. Hence the check below.
             * @noteDate 2014-04-03
             */

            /** @noinspection PhpUndefinedClassInspection */
            if (class_exists('\\V8JsTimeLimitException', false) && ($ex instanceof \V8JsTimeLimitException)) {
                /** @var \Exception $ex */
                Log::error($message = "Timeout while running script '$identifier': $message");
            } else {
                /** @noinspection PhpUndefinedClassInspection */
                if (class_exists('\\V8JsMemoryLimitException', false) && $ex instanceof \V8JsMemoryLimitException) {
                    Log::error($message = "Out of memory while running script '$identifier': $message");
                } else {
                    Log::error($message = "Exception executing javascript: $message");
                }
            }
        } /** @noinspection PhpUndefinedClassInspection */
        catch (\V8JsScriptException $ex) {
            $message = $ex->getMessage();

            /**
             * @note     V8JsTimeLimitException was released in a later version of the libv8 library than is supported by the current PECL v8js extension. Hence the check below.
             * @noteDate 2014-04-03
             */

            /** @noinspection PhpUndefinedClassInspection */
            if (class_exists('\\V8JsTimeLimitException', false) && ($ex instanceof \V8JsTimeLimitException)) {
                /** @var \Exception $ex */
                Log::error($message = "Timeout while running script '$identifier': $message");
            } else {
                /** @noinspection PhpUndefinedClassInspection */
                if (class_exists('\\V8JsMemoryLimitException', false) && $ex instanceof \V8JsMemoryLimitException) {
                    Log::error($message = "Out of memory while running script '$identifier': $message");
                } else {
                    Log::error($message = "Exception executing javascript: $message");
                }
            }
        }

        return null;
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
                $service = ServiceManager::getService($serviceName);
                $result = $service->handleRequest($request, $resource);
            }
        } catch (\Exception $ex) {
            $result = ResponseFactory::createWithException($ex);

            Log::error('Exception: ' . $ex->getMessage(), ['response' => $result]);
        }

        /**
         * For some mysterious reason the v8 library produces segmentation fault for PHP 7
         * when $result or content of the $result object (ServiceResponse) is used directly below. However,
         * when $result or content of the $result object is re-constructed into a array using the
         * code below it magically works!
         */
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            if ($result instanceof ServiceResponseInterface) {
                $content = $result->getContent();
                if (is_array($content)) {
                    $content = static::reBuildArray($content);
                }
                $result->setContent($content);
            } elseif (is_array($result)) {
                $result = static::reBuildArray($result);
            }
        }

        return ResponseFactory::sendScriptResponse($result);
    }

    /**
     * Process a single script
     *
     * @param string $path            The path/to/the/script to read and execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeScript($path, $identifier, array &$data = [], array $engineArguments = [])
    {
        return $this->executeString(static::loadScript($identifier, $path, true), $identifier, $data, $engineArguments);
    }

    /**
     * @param string $module The name of the module to load
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @return mixed
     */
    public static function loadScriptingModule($module)
    {
        $fullScriptPath = false;

        //  Remove any quotes from this passed in module
        $module = trim(str_replace(["'", '"'], null, $module), ' /');

        //  Check the configured script paths
        if (null === ($script = array_get(static::$libraries, $module))) {
            $script = $module;
        }

        foreach (static::$libraryPaths as $key => $path) {
            $checkScriptPath = $path . DIRECTORY_SEPARATOR . $script;

            if (is_file($checkScriptPath) && is_readable($checkScriptPath)) {
                $fullScriptPath = $checkScriptPath;
                break;
            }
        }

        if (!$script || !$fullScriptPath) {
            throw new InternalServerErrorException(
                'The module "' . $module . '" could not be found in any known locations.'
            );
        }

        $content = file_get_contents($fullScriptPath);

        return $content;
    }

    /**
     * Registers all distribution library modules as extensions.
     * These can be accessed from scripts like this:
     *
     * require("lodash");
     *
     * var a = [ 'one', 'two', 'three' ];
     *
     * _.each( a, function( element ) {
     *      print( "Found " + element + " in array\n" );
     * });
     *
     * Please note that this requires a version of the V8 library equal to or above 0.2.0.
     *
     * @param array $extensions
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected static function registerExtensions(array $extensions = [])
    {
        /** @noinspection PhpUndefinedClassInspection */
        $existing = \V8Js::getExtensions();
        $registered = array_diff($extensions, $existing);

        foreach ($registered as $module) {
            /** @noinspection PhpUndefinedClassInspection */
            if (false === \V8Js::registerExtension($module, static::loadScriptingModule($module), [], false)) {
                throw new InternalServerErrorException('Failed to register V8Js extension script: ' . $module);
            }
        }

        return $registered;
    }

    /**
     * @param string $script
     * @param array  $data
     * @param array  $platform
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @return string
     */
    protected function enrobeScript($script, array &$data = [], array $platform = [])
    {
        /**
         * For some mysterious reason the v8 library produces segmentation fault for PHP 7
         * when $platform array is used directly below. However,
         * when $platform array is re-constructed using the
         * code below it magically works!
         */
        if (version_compare(PHP_VERSION, '7.0.0') >= 0) {
            $platform = static::reBuildArray($platform);
        }
        $this->engine->platform = $platform;

        $jsonEvent = json_encode($data, JSON_UNESCAPED_SLASHES);

        //  Load user libraries
        $requiredLibraries = \Cache::get('scripting.libraries.v8js.required', null);

        $enrobedScript = <<<JS

//noinspection BadExpressionStatementJS
{$requiredLibraries};

_wrapperResult = (function() {

    //noinspection JSUnresolvedVariable
    var _event = {$jsonEvent};

	try	{
        //noinspection JSUnresolvedVariable
        _event.script_result = (function(event, platform) {

            //noinspection BadExpressionStatementJS,JSUnresolvedVariable
            {$script};
    	})(_event, DSP.platform);
	}
	catch ( _ex ) {
		_event.script_result = {error:_ex.message};
		_event.exception = _ex;
	}

	return _event;

})();

JS;

        return $enrobedScript;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if ($this->engine) {
            return call_user_func_array([$this->engine, $name], $arguments);
        }

        return null;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return mixed
     */
    public static function __callStatic($name, $arguments)
    {
        return call_user_func_array(['\\V8Js', $name], $arguments);
    }

    /**
     * Re-builds an array recursively.
     *
     * @param $array
     *
     * @return array
     */
    public static function reBuildArray($array)
    {
        $newArray = [];

        foreach ($array as $k => $v) {
            if (is_array($v)) {
                $newArray[$k] = static::reBuildArray($v);
            } else {
                $newArray[$k] = $v;
            }
        }

        return $newArray;
    }
}