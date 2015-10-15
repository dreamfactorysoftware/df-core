<?php
namespace DreamFactory\Core\Scripting;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Events\Exceptions\ScriptException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;

/**
 * Scripting engine
 */
class ScriptEngineManager
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @type int The cache TTL for the scripting store
     */
    const SESSION_STORE_TTL = 60;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Array of running script engines
     */
    protected static $instances = [];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Registers various available extensions to the v8 instance...
     *
     * @param array $engine_config
     * @param array $script_config
     *
     * @return ScriptingEngineInterface
     * @throws ServiceUnavailableException
     */
    public static function create(array $engine_config, $script_config = null)
    {
        $engineClass = ArrayUtils::get($engine_config, 'class_name');

        if (empty($engineClass) || !class_exists($engineClass)) {
            throw new ServiceUnavailableException("Failed to find script engine class '$engineClass'.");
        }

        $engine = new $engineClass($script_config);

        //  Stuff it in our instances array
        static::$instances[spl_object_hash($engine)] = $engine;

        return $engine;
    }

    /**
     * Publicly destroy engine
     *
     * @param ScriptingEngineInterface $engine
     */
    public static function destroy($engine)
    {
        $hash = spl_object_hash($engine);

        if (isset(static::$instances[$hash])) {
            unset(static::$instances[$hash]);
        }

        unset($engine);
    }

    /**
     * @param string $script        The script to run or a script file name
     * @param string $identifier    The name of this script
     * @param array  $engine_config The config of the script engine to run
     * @param array  $config        The config for this particular script
     * @param array  $data          The additional data as it will be exposed to script
     * @param string $output        Any output of the script
     *
     * @return array
     * @throws ScriptException
     * @throws ServiceUnavailableException
     */
    public static function runScript(
        $script,
        $identifier,
        array $engine_config,
        array $config = [],
        array &$data = [],
        &$output = null
    ){
        if (!empty($disable = config('df.scripting.disable')))
        {
            switch (strtolower($disable)){
                case 'all':
                    throw new ServiceUnavailableException("All scripting is disabled for this instance.");
                    break;
                default:
                    $type = (isset($engine_config['name'])) ? $engine_config['name'] : null;
                    if (!empty($type) && (false !== stripos($disable, $type))){
                        throw new ServiceUnavailableException("Scripting with $type is disabled for this instance.");
                    }
                    break;
            }
        }

        $engine = static::create($engine_config, $config);

        $result = $message = false;

        try {
            //  Don't show output
            ob_start();

            if (is_file($script)) {
                $result = $engine->executeScript($script, $identifier, $data, $config);
            } else {
                $result = $engine->executeString($script, $identifier, $data, $config);
            }
        } catch (ScriptException $ex) {
            $message = $ex->getMessage();

            \Log::error($message = "Exception executing script: $message");
        }

        //  Clean up
        $output = ob_get_clean();
        static::destroy($engine);

        if (boolval(\Config::get('df.log_script_memory_usage', false))) {
            \Log::debug('Engine memory usage: ' . static::resizeBytes(memory_get_usage(true)));
        }

        if (false !== $message) {
            throw new ScriptException($message, $output);
        }

        return $result;
    }

    /**
     * Converts single bytes into proper form (kb, gb, mb, etc.) with precision 2 (i.e. 102400 > 100.00kb)
     * Found on php.net's memory_usage page
     *
     * @param int $bytes
     *
     * @return string
     */
    public static function resizeBytes($bytes)
    {
        static $units = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];

        /** @noinspection PhpIllegalArrayKeyTypeInspection */

        return @round($bytes / pow(1024, ($i = floor(log($bytes, 1024)))), 2) . $units[$i];
    }
}
