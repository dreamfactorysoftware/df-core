<?php
namespace DreamFactory\Core\Components;

/**
 * Abstract class for command executed engines, i.e. those outside of PHP control
 */
trait PhpExecutable
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string Command executable to use.
     */
    protected $commandName;
    /**
     * @var string Where command executable can be found.
     */
    protected $commandPath;
    /**
     * @var string Where command executable can be found.
     */
    protected $fileExtension;
    /**
     * @var string|array Default arguments to pass to command line.
     */
    protected $arguments;
    /**
     * @var boolean Whether the command executable allows passing the script inline.
     */
    protected $supportsInlineExecution = false;
    /**
     * @var string|array Arguments to be applied when executing script inline instead of by file.
     */
    protected $inlineArguments;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     *
     * @throws \Exception
     */
    public function configure(array $settings = [])
    {
        if (empty($this->commandName = array_get($settings, 'command_name'))) {
            throw new \Exception("Invalid configuration: missing command name.");
        }

        // Various ways to figure out how to run this thing
        // check settings, then global config, then use the OS to find it
        $this->commandPath = array_get($settings, 'command_path');
        if (empty($this->commandPath)) {
            $this->commandPath = $this->findCommandPath();
            if (empty($this->commandPath = $this->commandName)) {
                throw new \Exception("Failed to find a valid path to command for scripting.");
            }
        }

        $this->arguments = array_get($settings, 'arguments');
        $this->supportsInlineExecution = boolval(array_get($settings, 'supports_inline_execution', false));
        $this->inlineArguments = array_get($settings, 'inline_arguments');
        $this->fileExtension = array_get($settings, 'file_extension');
    }

    public function execute($command, array &$output = null, &$return = null)
    {
        return @exec($command, $output, $return);
    }

    protected function findCommandPath()
    {
        if (!empty($this->commandName)) {
            if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
                // need to use windows where.exe...
                $finder = 'where ' . $this->commandName;
            } else {
                // must be linux or osx (darwin), use which
                $finder = 'which ' . $this->commandName;
            }

            return trim(shell_exec($finder));
        }

        return null;
    }

    protected function buildCommand($payload = '', $storage_location = '')
    {
        if ((strncasecmp(PHP_OS, 'WIN', 3) == 0) &&
            (false !== strpos($this->commandPath, ' ')) &&
            (false === strpos($this->commandPath, '"'))
        ) {
            // need to quote for windows when spaces are in path
            $runnerShell = '"' . $this->commandPath . '"';
        } else {
            $runnerShell = $this->commandPath;
        }

        if (is_array($this->arguments)) {
            foreach ($this->arguments as $argument) {
                $runnerShell .= ' ' . $argument;
            }
        } elseif (is_string($this->arguments)) {
            $runnerShell .= ' ' . $this->arguments;
        }

        // Windows behavior is unpredictable at best with escaped shell commands,
        // workable solution is to save to file and execute from file
        //
        // Also, when script is too big (huge data set in script) it won't run
        // on command line. Solution is to write the script to a file and execute the script file.
        $scriptInlineCharLimit = (integer) config('df.script_inline_char_limit');
        $scriptSize = strlen($payload);
        if (!$this->supportsInlineExecution || (substr(PHP_OS, 0, 3) == 'WIN') || ($scriptSize > $scriptInlineCharLimit)) {
            if (!empty($payload)) {
                if (empty($storage_location)) {
                    $storage_location = storage_path() . DIRECTORY_SEPARATOR . uniqid($this->commandName . "_", true);
                    if (is_string($this->fileExtension) && !empty($this->fileExtension)) {
                        $storage_location .= '.' . $this->fileExtension;
                    }
                    $this->scriptFile = $storage_location;
                }
                file_put_contents($storage_location, $payload);
                $runnerShell .= ' ' . $storage_location;
            }
        } else {
            if (is_array($this->inlineArguments)) {
                foreach ($this->inlineArguments as $argument) {
                    $runnerShell .= ' ' . $argument;
                }
            } elseif (is_string($this->inlineArguments)) {
                $runnerShell .= ' ' . $this->inlineArguments;
            }

            if (!empty($payload)) {
                $runnerShell .= ' ' . escapeshellarg($payload);
            }
        }

        return $runnerShell;
    }
}