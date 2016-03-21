<?php

namespace DreamFactory\Core\Scripting\Engines;

use Log;

class Python extends ExecutedEngine
{
    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@inheritdoc}
     */
    public function __construct(array $settings = [])
    {
        if (!isset($settings['command_name'])){
            $settings['command_name'] = 'python';
        }
        if (!isset($settings['command_path'])){
            $settings['command_path'] = config('df.scripting.python_path');
        }
        if (!isset($settings['supports_inline_execution'])){
            $settings['supports_inline_execution'] = true;
            $settings['inline_arguments'] = '-c';
        }

        parent::__construct($settings);
    }

    protected function processOutput(array &$data = [], array $output = [], $returned = null)
    {
        if (is_array($output)) {
            foreach ($output as $item) {
                if (is_string($item)) {
                    if ((strlen($item) > 10) && (false !== substr_compare($item, '{"request"', 0, 10))) {
                        $item =
                            str_replace(["'{", "}',", "'", "True", "False", ":None", ": None"],
                                ["{", "},", "\"", "true", "false", ":null", ": null"], $item);
                        $data = json_decode($item, true);
                    } else {
                        echo $item;
                    }
                }
            }
        } elseif (is_string($output)) {
            if ((strlen($output) > 10) && (0 === substr_compare($output, '{"request"', 0, 10))) {
                $output =
                    str_replace(["'{", "}',", "'", "True", "False", "None"], ["{", "},", "\"", "true", "false", "null"],
                        $output);
                $data = json_decode($output, true);
            } else {
                echo $output;
            }
        }

        if ($data === null) {
            if (is_array($output)) {
                $output = print_r($output, true);
            }
            Log::warning('Python script returned unsupported format: ' . $output);
        }

        return $data;
    }

    protected function enrobeScript($script, array &$data = [], array $platform = [])
    {
        $jsonEvent = json_encode($data, JSON_UNESCAPED_SLASHES);
        $jsonEvent = str_replace(['null', 'true', 'false'], ['None', 'True', 'False'], $jsonEvent);
        $jsonPlatform = json_encode($platform, JSON_UNESCAPED_SLASHES);
        $jsonPlatform = str_replace(['null', 'true', 'false'], ['None', 'True', 'False'], $jsonPlatform);
        $scriptLines = explode("\n", $script);

        $enrobedScript = <<<python
event = $jsonEvent;
platform = $jsonPlatform;

try:
    def my_closure(event, platform):
python;
        foreach ($scriptLines as $sl) {
            $enrobedScript .= "\n        " . $sl;
        }

        $enrobedScript .= <<<python

    event['script_result'] =  my_closure(event, platform);
except Exception as e:
    event['script_result'] = {'error':str(e)};
    event['exception'] = str(e)

print event;
python;
        $enrobedScript = trim($enrobedScript);

        return $enrobedScript;
    }
}