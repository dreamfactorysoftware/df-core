<?php

namespace DreamFactory\Core\Scripting\Engines;

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
        if (!isset($settings['command_name'])) {
            $settings['command_name'] = 'python';
        }
        if (!isset($settings['command_path'])) {
            $settings['command_path'] = config('df.scripting.python_path');
        }
        if (!isset($settings['file_extension'])) {
            $settings['file_extension'] = 'py';
        }
        if (!isset($settings['supports_inline_execution'])) {
            $settings['supports_inline_execution'] = true;
            $settings['inline_arguments'] = '-c';
        }

        parent::__construct($settings);
    }

    protected function transformOutputStringToData($output)
    {
        $output = str_replace(
            ["'{", "}',", "'", "True", "False", "None"],
            ["{", "},", "\"", "true", "false", "null"],
            $output
        );

        return parent::transformOutputStringToData($output);
    }

    protected function enrobeScript($script, array &$data = [], array $platform = [])
    {
        $jsonEvent = json_encode($data, JSON_UNESCAPED_SLASHES);
        $jsonEvent = str_replace(['null', 'true', 'false'], ['None', 'True', 'False'], $jsonEvent);
        $jsonPlatform = json_encode($platform, JSON_UNESCAPED_SLASHES);
        $jsonPlatform = str_replace(['null', 'true', 'false'], ['None', 'True', 'False'], $jsonPlatform);
        $scriptLines = explode("\n", $script);

        $enrobedScript = <<<python
import httplib, json;
from bunch import bunchify, unbunchify;

eventJson = $jsonEvent;
platformJson = $jsonPlatform;

_event = bunchify(eventJson);
_platform = bunchify(platformJson);

__host = _event.request.headers.host[0];
__headers = {
    'x-dreamfactory-api-key':_platform.session.api_key,
    'x-dreamfactory-session-token':_platform.session.session_token
    };

class Api:
	def __init__(self, host, header):
		self.host = host;
		self.header = header;
		self.conn = httplib.HTTPConnection(host);

	def get(self, path):
		return self.call('GET', path);

	def post(self, path, payload=''):
		return self.call('POST', path, payload);

	def put(self, path, payload=''):
		return self.call('PUT', path, payload);

	def patch(self, path, payload=''):
		return self.call('PATCH', path, payload);

	def delete(self, path, payload=''):
		return self.call('DELETE', path, payload);

	def call(self, verb, path, payload=''):
		self.conn.request(verb, path, payload, self.header);
		response = self.conn.getresponse();
		return response.read();
		
_platform.api = Api(__host, __headers);

try:
    def my_closure(event, platform):
python;
        foreach ($scriptLines as $sl) {
            $enrobedScript .= "\n        " . $sl;
        }

        $enrobedScript .= <<<python

    _event.script_result =  my_closure(_event, _platform);
except Exception as e:
    _event.script_result = {'error':str(e)};
    _event.exception = str(e)

print unbunchify(_event);
python;
        $enrobedScript = trim($enrobedScript);

        return $enrobedScript;
    }

    /** @inheritdoc */
    protected function checkOutputStringForData($output)
    {
        return ((strlen($output) > 10) && (false !== strpos($output, 'request')));
    }
}