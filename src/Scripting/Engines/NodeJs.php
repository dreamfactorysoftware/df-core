<?php
namespace DreamFactory\Core\Scripting\Engines;

/**
 * Plugin for the Node Javascript engine
 */
class NodeJs extends ExecutedEngine
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Array of extension names to preload with script.
     */
    protected $extensions;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@inheritdoc}
     */
    public function __construct(array $settings = [])
    {
        if (!isset($settings['command_name'])){
            $settings['command_name'] = 'node';
        }
        if (!isset($settings['command_path'])){
            $settings['command_path'] = config('df.scripting.nodejs_path');
        }
        if (!isset($settings['file_extension'])){
            $settings['file_extension'] = 'js';
        }
        if (!isset($settings['supports_inline_execution'])){
            $settings['supports_inline_execution'] = true;
            $settings['inline_arguments'] = '-e';
        }

        parent::__construct($settings);

        $extensions = array_get($settings, 'extensions', []);
        // accept comma-delimited string
        $this->extensions =
            (is_string($extensions)) ? array_map('trim', explode(',', trim($extensions, ','))) : $extensions;
    }

    /**
     * {@inheritdoc}
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

        return $enrobedScript;
    }
}