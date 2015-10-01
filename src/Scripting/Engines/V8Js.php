<?php
namespace DreamFactory\Core\Scripting\Engines;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Scripting\BaseEngineAdapter;
use \Log;

/**
 * Plugin for the php-v8js extension which exposes the V8 Javascript engine
 */
class V8Js extends BaseEngineAdapter implements ScriptingEngineInterface
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

        $name = ArrayUtils::get($settings, 'name', self::EXPOSED_OBJECT_NAME, true);
        $variables = ArrayUtils::get($settings, 'variables', [], true);
        $extensions = ArrayUtils::get($settings, 'extensions', [], true);
        // accept comma-delimited string
        $extensions = (is_string($extensions)) ? array_map('trim', explode(',', trim($extensions, ','))) : $extensions;
        $reportUncaughtExceptions = ArrayUtils::getBool($settings, 'report_uncaught_exceptions', false);
        $logMemoryUsage = ArrayUtils::getBool($settings, 'log_memory_usage', false);

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
     * @return mixed
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
        if (null !== $extensions = ArrayUtils::get($options, 'extensions', [], true)) {
            // accept comma-delimited string
            $extensions =
                (is_string($extensions)) ? array_map('trim', explode(',', trim($extensions, ','))) : $extensions;
            static::registerExtensions($extensions);
        }
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
        if (null === ($script = ArrayUtils::get(static::$libraries, $module))) {
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
     * Please note that this requires a version of the V8 library above any that are currently
     * distributed with popular distributions. As such, if this feature is not available
     * (module loading), the "lodash" library will be automatically registered and injected
     * into all script contexts.
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
//        $this->engine->event = $data;
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

            //noinspection CoffeeScriptUnusedLocalSymbols,JSUnusedLocalSymbols
            var include = function( fileName ) {
                var _contents;

                //noinspection JSUnresolvedFunction
                if ( false === ( _contents = platform.api.includeScript(fileName) ) ) {
                    throw 'Included script "' + fileName + '" not found.';
                }

                return _contents;
            };

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

        if (!static::$moduleLoaderAvailable) {
            $enrobedScript =
                \Cache::get('scripting.v8.extensions', static::loadScriptingModule('lodash')) . ';' . $enrobedScript;
        }

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
}