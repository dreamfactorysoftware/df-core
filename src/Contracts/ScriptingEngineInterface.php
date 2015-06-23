<?php
namespace DreamFactory\Core\Contracts;

/**
 * Something that can execute scripts
 */
interface ScriptingEngineInterface
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Handle setup for global/all instances of engine
     *
     * @param array $options
     *
     * @return mixed
     */
    public static function startup($options = null);

    /**
     * Process a single script
     *
     * @param string $script          The content of a script to execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information passed to this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeString($script, $identifier, array &$data = [], array $engineArguments = []);

    /**
     * Process a single script from a file path
     *
     * @param string $path            The path/to/the/script to read and execute
     * @param string $identifier      A string identifying this script
     * @param array  $data            An array of information passed to this script
     * @param array  $engineArguments An array of arguments to pass when executing the string
     *
     * @return mixed
     */
    public function executeScript($path, $identifier, array &$data = [], array $engineArguments = []);

    /**
     * Handle cleanup for global/all instances of engine
     *
     * @return mixed
     */
    public static function shutdown();
}
