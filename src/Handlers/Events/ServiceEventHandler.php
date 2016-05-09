<?php
namespace DreamFactory\Core\Handlers\Events;

use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Events\ResourcePreProcess;
use DreamFactory\Core\Events\ResourcePostProcess;
use DreamFactory\Core\Events\ServicePreProcess;
use DreamFactory\Core\Events\ServicePostProcess;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\RestException;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Utility\Session;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Log;
use ScriptEngineManager;

class ServiceEventHandler
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     *
     * @return array
     */
    public function subscribe($events)
    {
        $events->listen(ServicePreProcess::class, static::class . '@onServicePreProcess');
        $events->listen(ServicePostProcess::class, static::class . '@onServicePostProcess');
        $events->listen(ResourcePreProcess::class, static::class . '@onResourcePreProcess');
        $events->listen(ResourcePostProcess::class, static::class . '@onResourcePostProcess');
    }

    /**
     * Handle service pre-process events.
     *
     * @param  ServicePreProcess $event
     *
     * @return bool
     */
    public function onServicePreProcess($event)
    {
        $name = $event->service . '.' . strtolower($event->request->getMethod()) . '.pre_process';
        Log::debug('Service event: ' . $name);

        return $this->onPreProcess($name, $event);
    }

    /**
     * Handle service post-process events.
     *
     * @param  ServicePostProcess $event
     *
     * @return bool
     */
    public function onServicePostProcess($event)
    {
        $name = $event->service . '.' . strtolower($event->request->getMethod()) . '.post_process';
        Log::debug('Service event: ' . $name);

        return $this->onPostProcess($name, $event);
    }

    /**
     * Handle resource pre-process events.
     *
     * @param  ResourcePreProcess $event
     *
     * @return bool
     */
    public function onResourcePreProcess($event)
    {
        $name =
            $event->service .
            '.' .
            $event->resourcePath .
            '.' .
            strtolower($event->request->getMethod()) .
            '.pre_process';
        Log::debug('Resource event: ' . $name);

        return $this->onPreProcess($name, $event);
    }

    /**
     * Handle resource post-process events.
     *
     * @param  ResourcePostProcess $event
     *
     * @return bool
     */
    public function onResourcePostProcess($event)
    {
        $name =
            $event->service .
            '.' .
            $event->resourcePath .
            '.' .
            strtolower($event->request->getMethod()) .
            '.post_process';
        Log::debug('Resource event: ' . $name);

        return $this->onPostProcess($name, $event);
    }

    /**
     * Handle pre-process events.
     *
     * @param string                               $name
     * @param ServicePreProcess|ResourcePreProcess $event
     *
     * @return bool
     */
    public function onPreProcess($name, $event)
    {
        $data = [
            'request'  => $event->request->toArray(),
            'resource' => $event->resource
        ];

        if (null !== $result = $this->handleEventScript($name, $data)) {
            // request only
            $event->request->mergeFromArray(array_get($result, 'request', []));
            if (array_get($result, 'stop_propagation', false)) {
                Log::info('  * Propagation stopped by script.');

                return false;
            }
        }

        return true;
    }

    /**
     * Handle post-process events.
     *
     * @param string                                 $name
     * @param ServicePostProcess|ResourcePostProcess $event
     *
     * @return bool
     */
    public function onPostProcess($name, $event)
    {
        $data = [
            'request'  => $event->request->toArray(),
            'resource' => $event->resource,
            'response' => ($event->response instanceof RedirectResponse) ? [] : $event->response->toArray()
        ];

        if (null !== $result = $this->handleEventScript($name, $data)) {
            // response only
            if ($event->response instanceof ServiceResponseInterface) {
                $event->response->mergeFromArray(array_get($result, 'response', []));
            } else {
                $event->response = array_get($result, 'response', []);
            }
            if (array_get($result, 'stop_propagation', false)) {
                Log::info('  * Propagation stopped by script.');

                return false;
            }
        }

        return true;
    }

    /**
     * @param string $name
     * @param array  $event
     *
     * @return array|null
     * @throws
     * @throws \DreamFactory\Core\Events\Exceptions\ScriptException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Core\Exceptions\RestException
     * @throws \DreamFactory\Core\Exceptions\ServiceUnavailableException
     */
    protected function handleEventScript($name, &$event)
    {
        $model = EventScript::whereName($name)->whereIsActive(true)->first();
        if (empty($model)) {
            return null;
        }

        $output = null;
        $content = $model->content;
        Session::replaceLookups($content, true);
        $config = (is_array($model->config) ? $model->config : []);

        $engine = ScriptEngineManager::makeEngine($model->type, $config);

        $result = $engine->runScript(
            $content,
            $name,
            (is_array($model->config) ? $model->config : []),
            $event,
            $output
        );

        if (!empty($output)) {
            Log::info("Script '$name' output:" . PHP_EOL . $output . PHP_EOL);
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
            if (is_array($directResponse) && (isset($directResponse['content']) || isset($directResponse['status_code']))) {
                $result['response'] = $directResponse;
            } else {

                // otherwise must be raw content, assumes 200
                $result['response']['content'] = $directResponse;
                $result['response']['content_changed'] = true;
            }
        }

        return $result;
    }
}
