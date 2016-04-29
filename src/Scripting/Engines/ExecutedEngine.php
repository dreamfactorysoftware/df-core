<?php
namespace DreamFactory\Core\Scripting\Engines;

use DreamFactory\Core\Components\PhpExecutable;
use DreamFactory\Core\Contracts\ScriptingEngineInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Scripting\BaseEngineAdapter;
use \Log;

/**
 * Abstract class for command executed engines, i.e. those outside of PHP control
 */
abstract class ExecutedEngine extends BaseEngineAdapter implements ScriptingEngineInterface
{
    use PhpExecutable;

    /**
     * @param array $settings
     *
     * @throws ServiceUnavailableException
     */
    public function __construct(array $settings = [])
    {
        parent::__construct($settings);

        try {
            $this->configure($settings);
        } catch (\Exception $ex) {
            throw new ServiceUnavailableException($ex->getMessage(), $ex->getCode(), $ex->getPrevious());
        }
        if (empty($this->commandName = array_get($settings, 'command_name'))) {
            throw new ServiceUnavailableException("Invalid configuration: missing command name.");
        }

        static::startup($settings);
    }

    /**
     * {@inheritdoc}
     */
    public function executeString($script, $identifier, array &$data = [], array $engineArguments = [])
    {
        $data['__tag__'] = 'exposed_event';

        $enrobedScript = $this->enrobeScript($script, $data, static::buildPlatformAccess($identifier));
        $filePath = $this->getWritablePath($identifier);
        $runnerShell = $this->buildCommand($enrobedScript, $filePath);

        $output = null;
        $return = null;
        try {
            $this->execute($runnerShell, $output, $return);
        } catch (\Exception $ex) {
            $message = $ex->getMessage();
            Log::error("Exception executing command based script: $message");

            return null;
        }

        if ($return > 0) {
            Log::debug("Executed script: $runnerShell");
            throw new InternalServerErrorException('Executed command returned with error code: ' . $return);
        }

        if (null !== $temp = $this->processOutput($output)) {
            $data = $temp;
        }

        return $data;
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

        return file_get_contents($fullScriptPath);
    }

    protected function getWritablePath($identifier)
    {
        $filePath = storage_path('scripting' . DIRECTORY_SEPARATOR . $identifier);
        if ($this->fileExtension) {
            $filePath .= '.' . $this->fileExtension;
        }

        return $filePath;
    }

    /**
     * @param array $output
     *
     * @return null|array
     */
    protected function processOutput(array $output = [])
    {
        $data = null;
        if (is_array($output)) {
            foreach ($output as $item) {
                if (is_string($item)) {
                    if ($this->checkOutputStringForData($item)) {
                        $data = $this->transformOutputStringToData($item);
                    } else {
                        echo $item . PHP_EOL;
                    }
                }
            }
        } elseif (is_string($output)) {
            if ($this->checkOutputStringForData($output)) {
                $data = $this->transformOutputStringToData($output);
            } else {
                echo $output . PHP_EOL;
            }
        }

        return $data;
    }

    protected function checkOutputStringForData($output)
    {
        return ((strlen($output) > 10) && (0 === substr_compare($output, '{"request"', 0, 10)));
    }

    protected function transformOutputStringToData($output)
    {
        return json_decode($output, true);
    }

    /**
     * @param string $script
     * @param array  $data
     * @param array  $platform
     *
     * @return string
     */
    abstract protected function enrobeScript($script, array &$data = [], array $platform = []);
}