<?php
namespace DreamFactory\Core\Scripting\Services;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Library\Utility\Enums\Verbs;
use Log;
use ScriptEngineManager;

/**
 * Script
 * Scripting as a Service
 */
class Script extends BaseRestService
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string $content Content of the script
     */
    protected $content;
    /**
     * @var string $engineType Type of the script
     */
    protected $engineType;
    /**
     * @var array $scriptConfig Configuration for the engine for this particular script
     */
    protected $scriptConfig;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new Script Service
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        $config = ArrayUtils::clean(ArrayUtils::get($settings, 'config'));
        Session::replaceLookups($config, true);

        if (!is_string($this->content = array_get($config, 'content'))) {
            $this->content = '';
        }
        
        if (empty($this->engineType = array_get($config, 'type'))) {
            throw new \InvalidArgumentException('Script engine configuration can not be empty.');
        }

        if (!is_array($this->scriptConfig = array_get($config, 'config', []))) {
            $this->scriptConfig = [];
        };
    }

    /**
     * @return bool|mixed
     * @throws
     * @throws \DreamFactory\Core\Events\Exceptions\ScriptException
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    protected function processRequest()
    {
        //	Now all actions must be HTTP verbs
        if (!Verbs::contains($this->action)) {
            throw new BadRequestException('The action "' . $this->action . '" is not supported.');
        }

        $data =
            [
                'request'  => $this->request->toArray(),
                'response' => [
                    'content'      => null,
                    'content_type' => null,
                    'status_code'  => ServiceResponseInterface::HTTP_OK
                ],
                'resource' => $this->resourcePath
            ];

        $logOutput = $this->request->getParameterAsBool('log_output', true);
        $output = null;
        $engine = ScriptEngineManager::makeEngine($this->engineType, $this->scriptConfig);

        $result = $engine->runScript(
            $this->content,
            'service.' . $this->name,
            $this->scriptConfig,
            $data,
            $output
        );

        if (!empty($output) && $logOutput) {
            Log::info("Script '{$this->name}' output:" . PHP_EOL . $output . PHP_EOL);
        }

        //  Bail on errors...
        if (!is_array($result)) {
            $message = 'Script did not return an expected format: ' . print_r($result, true);
            // Should this return to client as error?
            Log::error($message);
            throw new InternalServerErrorException($message);
        }

        if (isset($result['exception'])) {
            $ex = $result['exception'];
            if ($ex instanceof \Exception) {
                throw $ex;
            } elseif (is_array($ex)) {
                $code = ArrayUtils::get($ex, 'code', null);
                $message = ArrayUtils::get($ex, 'message', 'Unknown scripting error.');
                $status = ArrayUtils::get($ex, 'status_code', ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
                throw new RestException($status, $message, $code);
            }
            throw new InternalServerErrorException(strval($ex));
        }

        // check for directly returned results, otherwise check for "response"
        $response = (isset($result['script_result']) ? $result['script_result'] : null);
        if (isset($response, $response['error'])) {
            if (is_array($response['error'])) {
                $msg = array_get($response, 'error.message');
            } else {
                $msg = $response['error'];
            }
            throw new InternalServerErrorException($msg);
        }

        if (empty($response)) {
            $response = (isset($result['response']) ? $result['response'] : null);
        }

        // check if this is a "response" array
        if (is_array($response) && isset($response['content'])) {
            $content = ArrayUtils::get($response, 'content');
            $contentType = ArrayUtils::get($response, 'content_type');
            $status = ArrayUtils::get($response, 'status_code', ServiceResponseInterface::HTTP_OK);

//            $format = ArrayUtils::get($response, 'format', DataFormats::PHP_ARRAY);

            return ResponseFactory::create($content, $contentType, $status);
        }

        // otherwise assume raw content
        return ResponseFactory::create($response);
    }

    public function getApiDocInfo()
    {
        return ['paths' => [], 'definitions' => []];
    }
}
