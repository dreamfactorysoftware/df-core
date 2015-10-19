<?php
namespace DreamFactory\Core\Scripting\Engines;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Scripting\BaseEngineAdapter;
use \Log;

/**
 * Plugin for the Node Javascript engine
 */
class NodeJs extends BaseEngineAdapter implements ScriptingEngineInterface
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type string The template for all module loading
     */
    const MODULE_LOADER_TEMPLATE = 'require("{module}");';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string Where NodeJs executable can be found.
     */
    protected $commandPath;
    /**
     * @var array Array of extension names to preload with script.
     */
    protected $extensions;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    public static function findCommandPath()
    {
        $path = config('df.scripting.nodejs_path');
        if (empty($path)) {
            if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
                // need to use windows where.exe...
                $finder = 'where node';
            } else {
                // must be linux or osx (darwin), use which
                $finder = 'which node';
            }
            $path = trim(shell_exec($finder));
        }

        return $path;
    }

    /**
     * @param array $settings
     *
     * @throws ServiceUnavailableException
     */
    public function __construct(array $settings = [])
    {
        parent::__construct($settings);

        if (empty($this->commandPath)) {
            $this->commandPath = static::findCommandPath();
            if (empty($this->commandPath)) {
                throw new ServiceUnavailableException("Failed to find a valid path to NodeJs.");
            }
        }

        $extensions = ArrayUtils::get($settings, 'extensions', [], true);
        // accept comma-delimited string
        $this->extensions = (is_string($extensions)) ? array_map('trim', explode(',', trim($extensions, ','))) : $extensions;

        static::startup($settings);
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

            $output = null;
            $return = null;
            $result = exec($runnerShell, $output, $return);
            if ($return > 0){
                throw new InternalServerErrorException('Node script returned with error code: ' . $return);
            }
            $outStr = implode('', $output);
            $outArr = json_decode($outStr, true);

            return $outArr;
        } catch (\Exception $ex) {
            $message = $ex->getMessage();

            Log::error($message = "Exception executing javascript: $message");
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

        return file_get_contents($fullScriptPath);
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
        $jsonEvent = json_encode($data, JSON_UNESCAPED_SLASHES);
        $jsonPlatform = json_encode($platform, JSON_UNESCAPED_SLASHES);

        //  Load user libraries
//        $requiredLibraries = \Cache::get('scripting.libraries.nodejs.required', null);

        $enrobedScript = <<<JS

_wrapperResult = (function() {

    //noinspection JSUnresolvedVariable
    var _event = {$jsonEvent};
    //noinspection JSUnresolvedVariable
    var _platform = {$jsonPlatform};

	try	{
        //noinspection JSUnresolvedVariable
        _event.script_result = (function(event, platform) {

            //noinspection BadExpressionStatementJS,JSUnresolvedVariable
            {$script};
    	})(_event, _platform);
	}
	catch ( _ex ) {
		_event.script_result = {error:_ex.message};
		_event.exception = _ex;
	}

	return _event;

})();
console.log(JSON.stringify(_wrapperResult));

JS;

//        if (!static::$moduleLoaderAvailable) {
//            $enrobedScript =
//                \Cache::get('scripting.nodejs.extensions', static::loadScriptingModule('lodash')) . ';' . $enrobedScript;
//        }

        return $this->commandPath . " -e '$enrobedScript'";
    }
}