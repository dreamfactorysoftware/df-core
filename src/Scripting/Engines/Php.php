<?php
namespace DreamFactory\Core\Scripting\Engines;

use DreamFactory\Core\Contracts\ScriptingEngineInterface;

/**
 * Wrapper around the php extension
 */
class Php implements ScriptingEngineInterface
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
    public function executeString($script, $identifier, $data, array $engineArguments = [])
    {
        // TODO: Implement executeString() method.
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
    public function executeScript($path, $identifier, $data, array $engineArguments = [])
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
}