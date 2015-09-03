<?php
namespace DreamFactory\Core\Scripting\Engines;

use DreamFactory\Core\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Scripting\BaseEngineAdapter;
use \Log;

/**
 * Wrapper around the php extension
 */
class Php extends BaseEngineAdapter implements ScriptingEngineInterface
{
    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return mixed
     */
    public static function startup($options = null)
    {
        // TODO: Implement startup() method.
    }

    /**
     * Process a single script
     *
     * @param string $script          The string to execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information about the event triggering this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @internal param string $eventName
     * @internal param \DreamFactory\Core\Events\PlatformEvent $event
     * @internal param \DreamFactory\Core\Events\EventDispatcher $dispatcher
     * @return mixed
     */
    public function executeString($script, $identifier, array &$data = [], array $engineArguments = [])
    {
        // TODO: Implement executeString() method.
        $data['__tag__'] = 'exposed_event';

        try {
            $platform = static::buildPlatformAccess($identifier);
            $event = &$data;

            $event['script_result'] = eval($script);

            return $event;
        } catch (\Exception $ex) {
            $message = $ex->getMessage();

            Log::error($message = "Exception executing PHP: $message");
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
        // TODO: Implement executeScript() method.

    }

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return mixed
     */
    public static function shutdown()
    {
        // TODO: Implement shutdown() method.
    }

    protected function safe_eval($code, &$status)
    { //status 0=failed,1=all clear
        //Signs
        //Can't assign stuff
        $bl_signs = ["="];

        //Language constructs
        $bl_constructs = [
            "print",
            "echo",
            "require",
            "include",
            "if",
            "else",
            "while",
            "for",
            "switch",
            "exit",
            "break"
        ];

        //Functions
        $funcs = get_defined_functions();
        $funcs = array_merge($funcs['internal'], $funcs['user']);

        //Functions allowed
        //Math cant be evil, can it?
        $whitelist = ["pow", "exp", "abs", "sin", "cos", "tan"];

        //Remove whitelist elements
        foreach ($whitelist as $f) {
            unset($funcs[array_search($f, $funcs)]);
        }
        //Append '(' to prevent confusion (e.g. array() and array_fill())
        foreach ($funcs as $key => $val) {
            $funcs[$key] = $val . "(";
        }
        $blacklist = array_merge($bl_signs, $bl_constructs, $funcs);

        //Check
        $status = 1;
        foreach ($blacklist as $nono) {
            if (strpos($code, $nono) !== false) {
                $status = 0;

                return 0;
            }
        }

        //Eval
        return @eval($code);
    }

}