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
        $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443)? "'https'" : "'http'";

        //  Load user libraries
        //$requiredLibraries = \Cache::get('scripting.libraries.nodejs.required', null);

        $enrobedScript = <<<JS

_wrapperResult = (function() {
    //noinspection JSUnresolvedVariable
    var _event = {$jsonEvent};
    //noinspection JSUnresolvedVariable
    var _platform = {$jsonPlatform};
    //noinspection JSUnresolvedVariable
    var _protocol = {$protocol};
    //noinspection JSUnresolvedVariable
    var _host = _event.request.headers.host;
    //request options
    var _options = {};
    
    function getProtocol(path) {
        path = path.trim(path);
        if(path.substring(0, 7) === 'http://'){
            return 'http'
        } else if(path.substring(0, 8) === 'https://'){
            return 'https';
        } else {
            return _protocol;
        }
    }
    
    function getHost(path){
        path = path.trim(path);
        if(path.substring(0, 7) === 'http://'){
            return path.substring(7).substring(0, path.substring(7).indexOf('/'));
        } else if(path.substring(0, 8) === 'https://'){
            return path.substring(8).substring(0, path.substring(8).indexOf('/'));
        } else {
            return _host;
        }
    }
    
    function isInternalApi(path){
        path = path.trim(path);
        return (path.substring(0, 7) === 'http://' || path.substring(0, 8) === 'https://')? false : true;
    }
    
    function cleanPath(path) {
        path = path.trim(path);
        if(isInternalApi(path)){
            if(path.substring(0, 1) !== '/'){
                path = '/'+path;
            }
            
            if(path.substring(0, 8) !== '/api/v2/'){
                path = '/api/v2'+path;
            }
        }
        return path;
    }
    
    _event.setReturn = function(content){
        _event.script_result = content;
        console.log(JSON.stringify(_event));
    };
    
    _event.setResponse = function(content, statusCode, contentType){
        if(!_event.response){
            _event.response = {};
        }
        if(!statusCode){
            statusCode = 200;
        }
        if(!contentType){
            contentType = 'application/json';
        }
        
        _event.response.content = content;
        _event.response.status_code = statusCode;
        _event.response.content_type = contentType;

        console.log(JSON.stringify(_event));
    }
    
    _platform.api = {
        call: function (verb, path, payload, options, callback) {
            options = (options===null || options===undefined)? '' : options;
            var headers = (options.headers)? options.headers : (options.parameters)? '' : options;
            
            var host = getHost(path);
            if(host.indexOf(':') !== -1){
                host = host.split(':');    
                _options.host = host[0];
                _options.port = host[1];
            } else {
                _options.host = host;
                if(_options.port) delete _options.port;
            }
            _options.method = verb;
            _options.path = cleanPath(path);
            
            if(options.parameters){
                for(var param in options.parameters){
                    if(_options.path.indexOf('?') === -1){
                        _options.path = _options.path + '?' + param + '=' + options.parameters[param];
                    } else {
                        _options.path = _options.path + '&' + param + '=' + options.parameters[param];
                    }
                }
            }
            
            if(!isInternalApi(path)){
                _options.headers = headers;
            } else {
                _options.headers = {
                    'x-dreamfactory-api-key': _platform.session.api_key,
                    'x-dreamfactory-session-token': _platform.session.session_token 
                }
                if(headers){
                    for(var header in headers){
                        if(!_options.headers[header]){
                            _options.headers[header] = headers[header];
                        }
                    }
                }
            }
            
            if(typeof payload === 'object'){
                payload = JSON.stringify(payload);
            }

            var _callback = function (response) {
                var body = '';

                response.on('data', function (chunk) {
                    body += chunk;
                });

                response.on('end', function () {
                    callback(body, response);
                });
            };

            var http = require(getProtocol(path));
            var request = http.request(_options, _callback);
            request.write(payload);
            request.end();
            
            return request;
        },
        get: function (path, options, callback) {
            return this.call('GET', path, '', options, callback);
        },
        post: function (path, payload, options, callback) {
            return this.call('POST', path, payload, options, callback);
        },
        put: function (path, payload, options, callback) {
            return this.call('PUT', path, payload, options, callback);
        },
        patch: function (path, payload, options, callback) {
            return this.call('PATCH', path, payload, options, callback);
        },
        delete: function (path, payload, options, callback) {
            return this.call('DELETE', path, payload, options, callback);
        }
    };
    
    process.on('uncaughtException', function(error){
        var content = {
            error : {
                message : error.message,
                code : 500
            }
        }
        _event.setResponse(content, 500, 'application/json');
    });

	try	{
        //noinspection JSUnresolvedVariable
        _event.script_result = (function(event, platform) {

            //noinspection BadExpressionStatementJS,JSUnresolvedVariable
            {$script};
    	})(_event, _platform);
	}
	catch ( _ex ) {
		_event.script_result = {error: _ex.message};
		_event.exception = {message: _ex.message, code: _ex.code};
	}

	return _event;

})();

console.log(JSON.stringify(_wrapperResult));
JS;

        return $enrobedScript;
    }
}