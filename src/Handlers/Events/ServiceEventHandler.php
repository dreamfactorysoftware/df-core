<?php
namespace DreamFactory\Rave\Handlers\Events;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Models\EventScript;
use DreamFactory\Rave\Scripting\ScriptEngine;
use Illuminate\Contracts\Events\Dispatcher;
use DreamFactory\Rave\Events\ResourcePreProcess;
use DreamFactory\Rave\Events\ResourcePostProcess;
use DreamFactory\Rave\Events\ServicePreProcess;
use DreamFactory\Rave\Events\ServicePostProcess;
use Illuminate\Contracts\Support\Arrayable;
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

    protected function handleEventScript( $name, $request, $response = null, $resource = null )
    {
        $model = EventScript::whereName( $name )->first();
        if ( !empty( $model ) )
        {
            $output = null;
            $data = [ 'request' => static::serviceRequestToArray( $request ), 'resource' => $resource ];
            if ( !is_null( $response ) )
            {
                $data['response'] = static::serviceResponseToArray( $response );
            }

            $result = ScriptEngine::runScript( $model->content, $name, $model->getEngineAttribute(), ArrayUtils::clean( $model->config ), $data, $output );

            return $output;
        }

        return null;
    }

    protected static function serviceRequestToArray( $request )
    {
        if ( $request instanceof ServiceRequestInterface )
        {
            return [
                'api_version' => $request->getApiVersion(),
                'method'      => $request->getMethod(),
                'parameters'  => $request->query(),
                'headers'     => $request->getHeader(),
                'payload'     => $request->getPayloadData(),
            ];
        }

        if ( is_array( $request ) )
        {
            return $request;
        }

        if ( $request instanceof Arrayable )
        {
            return $request->toArray();
        }

        return [ ];
    }

    protected static function serviceResponseToArray( $response )
    {
        if ( $response instanceof ServiceResponseInterface )
        {
            return [
                'status_code'  => $response->getStatusCode(),
                'content_type' => $response->getContentType(),
                'content'      => $response->getContent(),
            ];
        }

        if ( is_array( $response ) )
        {
            return $response;
        }

        if ( $response instanceof Arrayable )
        {
            return $response->toArray();
        }

        return [ ];
    }
}
