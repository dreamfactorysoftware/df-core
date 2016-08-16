<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\HttpStatusCodeInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use ScriptEngineManager;
use Log;

trait ScriptHandler
{
    /**
     * @param string  $identifier
     * @param string  $script
     * @param string  $type
     * @param array   $config
     * @param array   $data
     * @param boolean $log_output
     *
     * @return array|null
     * @throws
     * @throws \DreamFactory\Core\Events\Exceptions\ScriptException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    public function handleScript($identifier, $script, $type, $config, &$data, $log_output = true)
    {
        $engine = ScriptEngineManager::makeEngine($type, $config);

        $output = null;
        $result = $engine->runScript($script, $identifier, $config, $data, $output);

        if ($log_output && !empty($output)) {
            Log::info("Script '$identifier' output:" . PHP_EOL . $output . PHP_EOL);
        }

        //  Bail on errors...
        if (!is_array($result)) {
            $message = "Script '$identifier' did not return an array: " . print_r($result, true);
            throw new InternalServerErrorException($message);
        }

        if (isset($result['exception'])) {
            $ex = $result['exception'];
            if ($ex instanceof \Exception) {
                throw $ex;
            } elseif (is_array($ex)) {
                $code = array_get($ex, 'code', null);
                $message = array_get($ex, 'message', 'Unknown scripting error.');
                $status = array_get($ex, 'status_code', HttpStatusCodeInterface::HTTP_INTERNAL_SERVER_ERROR);
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

        return $response;
    }
}