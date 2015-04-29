<?php
namespace DreamFactory\Rave\Handlers\Events;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Models\EventScript;
use DreamFactory\Rave\Scripting\ScriptEngine;
use Illuminate\Contracts\Events\Dispatcher;
use DreamFactory\Rave\Events\ResourcePreProcess;
use DreamFactory\Rave\Events\ResourcePostProcess;
use DreamFactory\Rave\Events\ServicePreProcess;
use DreamFactory\Rave\Events\ServicePostProcess;
use \Log;

class ServiceEventHandler
{
    /**
     * Handle service pre-process events.
     *
     * @param  ServicePreProcess $event
     *
     * @return bool
     */
    public function onServicePreProcess( $event )
    {
        $name = $event->service . '.' . strtolower( $event->verb ) . '.pre_process';
        Log::debug( 'Service event: ' . $name );

        return $this->handleEventScript( $name, $event->request, null, $event->resource );
    }

    /**
     * Handle service post-process events.
     *
     * @param  ServicePostProcess $event
     *
     * @return bool
     */
    public function onServicePostProcess( $event )
    {
        $name = $event->service . '.' . strtolower( $event->verb ) . '.post_process';
        Log::debug( 'Service event: ' . $name );

        return $this->handleEventScript( $name, $event->request, $event->response, $event->resource );
    }

    /**
     * Handle resource pre-process events.
     *
     * @param  ResourcePreProcess $event
     *
     * @return bool
     */
    public function onResourcePreProcess( $event )
    {
        $name = $event->service . '.' . $event->resourcePath . '.' . strtolower( $event->verb ) . '.pre_process';
        Log::debug( 'Resource event: ' . $name );

        return $this->handleEventScript( $name, $event->request, null, $event->resource );
    }

    /**
     * Handle resource post-process events.
     *
     * @param  ResourcePostProcess $event
     *
     * @return bool
     */
    public function onResourcePostProcess( $event )
    {
        $name = $event->service . '.' . $event->resourcePath . '.' . strtolower( $event->verb ) . '.post_process';
        Log::debug( 'Resource event: ' . $name );

        return $this->handleEventScript( $name, $event->request, $event->response, $event->resource );
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @param  Dispatcher $events
     *
     * @return array
     */
    public function subscribe( $events )
    {
        $events->listen( 'DreamFactory\Rave\Events\ServicePreProcess', 'DreamFactory\Rave\Handlers\Events\ServiceEventHandler@onServicePreProcess' );
        $events->listen( 'DreamFactory\Rave\Events\ServicePostProcess', 'DreamFactory\Rave\Handlers\Events\ServiceEventHandler@onServicePostProcess' );
        $events->listen( 'DreamFactory\Rave\Events\ResourcePreProcess', 'DreamFactory\Rave\Handlers\Events\ServiceEventHandler@onResourcePreProcess' );
        $events->listen( 'DreamFactory\Rave\Events\ResourcePostProcess', 'DreamFactory\Rave\Handlers\Events\ServiceEventHandler@onResourcePostProcess' );
    }

    /**
     * @param string                   $name
     * @param ServiceRequestInterface  $request
     * @param ServiceResponseInterface $response
     * @param mixed                    $resource
     *
     * @return bool|null
     * @throws InternalServerErrorException
     * @throws \DreamFactory\Rave\Events\Exceptions\ScriptException
     */
    protected function handleEventScript( $name, $request, $response = null, $resource = null )
    {
        $model = EventScript::whereName( $name )->first();
        if ( !empty( $model ) )
        {
            $output = null;
            $data = [ 'request' => $request->toArray(), 'resource' => $resource ];
            if ( !is_null( $response ) )
            {
                $data['response'] = $response->toArray();
            }

            $result = ScriptEngine::runScript( $model->content, $name, $model->getEngineAttribute(), ArrayUtils::clean( $model->config ), $data, $output );

            //  Bail on errors...
            if ( is_array( $result ) && ( isset( $result['error'] ) || isset( $result['exception'] ) ) )
            {
                throw new InternalServerErrorException( ArrayUtils::get( $result, 'exception', ArrayUtils::get( $result, 'error' ) ) );
            }

            //  The script runner should return an array
            if ( is_array( $result ) && isset( $result['__tag__'] ) )
            {
                // feed back the request and response
                if ( is_null( $response ) )
                {
                    // request only
                    $request->mergeFromArray(ArrayUtils::get($result, 'request', []));
                }
                else
                {
                    // response only
                    $response->mergeFromArray(ArrayUtils::get($result, 'response', []));
                }
            }
            else
            {
                Log::error( '  * Script did not return an array: ' . print_r( $result, true ) );
            }

            if ( !empty( $_output ) )
            {
                Log::info( '  * Script "' . $name . '" output:' . PHP_EOL . $_output . PHP_EOL );
            }

            if ( ArrayUtils::get( $result, 'stop_propagation', false ) )
            {
                Log::info( '  * Propagation stopped by script.' );

                return false;
            }

            return $output;
        }

        return null;
    }
}
