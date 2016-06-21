<?php
namespace DreamFactory\Core\Events;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Utility\Session;
use Illuminate\Queue\SerializesModels;
use Log;
use ScriptEngineManager;

abstract class ApiEvent
{
    use SerializesModels;

    public $path;

    public $request;

    public $resource;

    abstract public function handle();

    /**
     * Create a new event instance.
     *
     * @param string                  $path
     * @param ServiceRequestInterface $request
     * @param mixed                   $resource
     */
    public function __construct($path, &$request, $resource = null)
    {
        $this->path = $path;
        $this->request = $request;
        $this->resource = $resource;
    }

    /**
     * Make name for event.
     *
     * @return string
     */
    protected function makeName()
    {
        return strtolower($this->path . '.' . $this->request->getMethod());
    }

    protected function makeData()
    {
        return [
            'request'  => $this->request->toArray(),
            'resource' => $this->resource
        ];
    }

    /**
     * @param string $name
     *
     * @return EventScript|null
     */
    protected function getEventScript($name)
    {
        if (empty($model = EventScript::whereName($name)->whereIsActive(true)->first())) {
            return null;
        }

        $model->content = Session::translateLookups($model->content, true);
        if (!is_array($model->config)) {
            $model->config = [];
        }

        return $model;
    }

    /**
     * @param EventScript $script
     * @param array       $event
     *
     * @return array|null
     * @throws
     * @throws \DreamFactory\Core\Events\Exceptions\ScriptException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    protected function handleEventScript($script, &$event)
    {
        $engine = ScriptEngineManager::makeEngine($script->type, $script->config);

        $output = null;
        $result = $engine->runScript($script->content, $script->name, $script->config, $event, $output);

        if (!empty($output)) {
            Log::info("Script '{$script->name}' output:" . PHP_EOL . $output . PHP_EOL);
        }

        //  Bail on errors...
        if (!is_array($result)) {
            // Should this return to client as error?
            Log::error('  * Script did not return an array: ' . print_r($result, true));

            return $result;
        }

        if (isset($result['exception'])) {
            $ex = $result['exception'];
            if ($ex instanceof \Exception) {
                throw $ex;
            } elseif (is_array($ex)) {
                $code = array_get($ex, 'code', null);
                $message = array_get($ex, 'message', 'Unknown scripting error.');
                $status = array_get($ex, 'status_code', ServiceResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
                throw new RestException($status, $message, $code);
            }
            throw new InternalServerErrorException(strval($ex));
        }

        // check for directly returned results, otherwise check for "response"
        $directResponse = (isset($result['script_result']) ? $result['script_result'] : null);
        if (isset($directResponse, $directResponse['error'])) {
            throw new InternalServerErrorException($directResponse['error']);
        }

        // check for "return" results
        if (!empty($directResponse)) {
            // could be formatted array or raw content
            if (is_array($directResponse) &&
                (isset($directResponse['content']) || isset($directResponse['status_code']))
            ) {
                $result['response'] = $directResponse;
            } else {
                // otherwise must be raw content, assumes 200
                $result['response']['content'] = $directResponse;
            }
        }

        return $result;
    }

    /**
     * @param EventScript $script
     * @param $result
     *
     * @return bool
     */
    protected function handleEventScriptResult(
        /** @noinspection PhpUnusedParameterInspection */
        $script, $result)
    {
        return true;
    }
}
