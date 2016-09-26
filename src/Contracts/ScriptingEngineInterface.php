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
     * @return void
     */
    public static function startup($options = null);

    /**
     * @param string $script     The script to run or a script file name
     * @param string $identifier The name of this script
     * @param array  $config     The config for this particular script
     * @param array  $data       The additional data as it will be exposed to script
     * @param string $output     Any output of the script
     *
     * @return array
     */
    public function runScript($script, $identifier, array $config = [], array &$data = [], &$output = null);

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
     * @return void
     */
    public static function shutdown();
}
