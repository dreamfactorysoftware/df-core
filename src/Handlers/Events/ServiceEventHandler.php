<?php
namespace DreamFactory\Core\Handlers\Events;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\EventScript;
use DreamFactory\Core\Scripting\ScriptEngineManager;
use Illuminate\Contracts\Events\Dispatcher;
use DreamFactory\Core\Events\ResourcePreProcess;
use DreamFactory\Core\Events\ResourcePostProcess;
use DreamFactory\Core\Events\ServicePreProcess;
use DreamFactory\Core\Events\ServicePostProcess;
use \Log;

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
            $event->request->mergeFromArray(ArrayUtils::get($result, 'request', []));
            if (ArrayUtils::get($result, 'stop_propagation', false)) {
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
            'response' => $event->response
        ];

        if (null !== $result = $this->handleEventScript($name, $data)) {
            // response only
            if ($event->response instanceof ServiceResponseInterface) {
                $event->response->mergeFromArray(ArrayUtils::get($result, 'response', []));
            } else {
                $event->response = ArrayUtils::get($result, 'response', []);
            }
            if (ArrayUtils::get($result, 'stop_propagation', false)) {
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
     * @throws InternalServerErrorException
     * @throws \DreamFactory\Core\Events\Exceptions\ScriptException
     */
    protected function handleEventScript($name, &$event)
    {
        $model = EventScript::with('script_type_by_type')->whereName($name)->whereIsActive(true)->first();
        if (!empty($model)) {
            $output = null;

            $result = ScriptEngineManager::runScript(
                $model->content,
                $name,
                $model->script_type_by_type->toArray(),
                ArrayUtils::clean($model->config),
                $event,
                $output
            );

            //  Bail on errors...
            if (is_array($result) && isset($result['script_result'], $result['script_result']['error'])) {
                throw new InternalServerErrorException($result['script_result']['error']);
            }
            if (is_array($result) && isset($result['exception'])) {
                throw new InternalServerErrorException(ArrayUtils::get($result, 'exception',''));
            }

            //  The script runner should return an array
            if (!is_array($result) || !isset($result['__tag__'])) {
                Log::error('  * Script did not return an array: ' . print_r($result, true));
            }

            if (!empty($output)) {
                Log::info('  * Script "' . $name . '" output:' . PHP_EOL . $output . PHP_EOL);
            }

            return $result;
        }

        return null;
    }
}
