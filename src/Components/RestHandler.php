<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Components;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Enums\ContentTypes;
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Contracts\ServiceRequestInterface;

/**
 * Class RestHandler
 *
 * @package DreamFactory\Rave\Components
 */
abstract class RestHandler
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const ACTION_TOKEN = '{action}';
    /**
     * @var string The default pattern of dispatch methods. Action token embedded.
     */
    const DEFAULT_HANDLER_PATTERN = 'handle{action}';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string API name
     */
    protected $name;
    /**
     * @var string Description of this service
     */
    protected $label;
    /**
     * @var string Description of this service
     */
    protected $description;
    /**
     * @var boolean Is this service activated for use?
     */
    protected $isActive = false;
    /**
     * @var string HTTP Action Verb
     */
    protected $action = Verbs::GET;
    /**
     * @var string HTTP Action Verb
     */
    protected $originalAction = null;
    /**
     * @var string Native format of output of service, null for php, otherwise json, xml, etc.
     */
    protected $nativeFormat = ContentTypes::PHP_ARRAY;
    /**
     * @var int|null Default output format, either null (native), or DataFormats enum value.
     */
    protected $outputFormat = ContentTypes::JSON;
    /**
     * @var string If set, prompt browser to download response as a file.
     */
    protected $outputAsFile = null;
    /**
     * @var string Resource name.
     */
    protected $resource;
    /**
     * @var mixed Resource ID.
     */
    protected $resourceId;
    /**
     * @var string Resource Path.
     */
    protected $resourcePath;
    /**
     * @var array Resource path exploded into array.
     */
    protected $resourceArray;
    /**
     * @var bool If true, processRequest() dispatches a call to handle[Action]() methods if defined.
     * For example, a GET request would be dispatched to handleGet().
     */
    protected $autoDispatch = true;
    /**
     * @var string The pattern to search for dispatch methods.
     * The string {action} will be replaced by the inbound action (i.e. Get, Put, Post, etc.)
     */
    protected $autoDispatchPattern = self::DEFAULT_HANDLER_PATTERN;
    /**
     * @var bool|array Array of verb aliases. Has no effect if $autoDispatch !== true
     *
     * Example:
     *
     * $this->verbAliases = array(
     *     static::Put => static::Post,
     *     static::Patch => static::Post,
     *     static::Merge => static::Post,
     *
     *     // Use a closure too!
     *     static::Get => function($resource){
     *    ...
     *   },
     * );
     *
     *    The result will be that processRequest() will dispatch a PUT, PATCH, or MERGE request to the POST handler.
     */
    protected $verbAliases = [];
    /**
     * @var ServiceResponseInterface Response object implementing the ServiceResponseInterface.
     */
    protected $response = null;
    /**
     * @var ServiceRequestInterface Request object implementing the ServiceRequestInterface.
     */
    protected $request = null;

    /**
     * @param array $settings
     */
    public function __construct( $settings = [ ] )
    {
        foreach ( $settings as $key => $value )
        {
            $this->{$key} = $value;
        }
    }

    /**
     * Runs pre process tasks/scripts
     */
    protected function preProcess()
    {

    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {

    }

    /**
     * @param ServiceRequestInterface $request
     * @param null                    $resource
     * @param int                     $outputFormat
     *
     * @return ServiceResponseInterface
     * @throws BadRequestException
     * @throws InternalServerErrorException
     */
    public function handleRequest( ServiceRequestInterface $request, $resource = null, $outputFormat = ContentTypes::JSON )
    {
        $this->setRequest( $request );
        $this->setAction( $request->getMethod() );
        $this->setResourceMembers( $resource );
        $this->setResponseFormat( $outputFormat );

        //  Perform any pre-request processing
        $this->preProcess();

        $this->response = ( empty( $this->resource ) ) ? $this->processRequest() : $this->handleResource();

        //	Inherent failure?
        if ( false === $this->response )
        {
            $message =
                $this->action .
                ' requests' .
                ( !empty( $this->resourcePath ) ? ' for resource "' . $this->resourcePath . '"' : ' without a resource' ) .
                ' are not currently supported by the "' .
                $this->name .
                '" service.';

            throw new BadRequestException( $message );
        }

        //  Perform any post-request processing
        $this->postProcess();

        //  Perform any response processing
        return $this->respond();
    }

    /**
     * @return bool|mixed
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    protected function handleResource()
    {
        //  Fall through is to process just like a no-resource request
        $resources = $this->getResources();
        if ( !empty( $resources ) && !empty( $this->resource ) )
        {
            $found = ArrayUtils::findByKeyValue( $resources, 'name', $this->resource );
            if ( isset( $found, $found['class_name'] ) )
            {
                $className = $found['class_name'];

                if ( !class_exists( $className ) )
                {
                    throw new InternalServerErrorException( 'Service configuration class name lookup failed for resource ' . $this->resourcePath );
                }

                /** @var RestHandler $resource */
                $resource = $this->instantiateResource( $className, $found );

                $newPath = $this->resourceArray;
                array_shift( $newPath );
                $newPath = implode( '/', $newPath );

                return $resource->handleRequest( $this->request, $newPath, $this->outputFormat );
            }

            throw new NotFoundException( "Resource '{$this->resource}' not found for service '{$this->name}'." );
        }

        return $this->processRequest();
    }

    protected function instantiateResource( $class, $info = [ ] )
    {
        return new $class( $info );
    }

    /**
     * @return bool|mixed
     * @throws BadRequestException
     */
    protected function processRequest()
    {
        //	Now all actions must be HTTP verbs
        if ( !Verbs::contains( $this->action ) )
        {
            throw new BadRequestException( 'The action "' . $this->action . '" is not supported.' );
        }

        $methodToCall = false;

        //	Check verb aliases as closures
        if ( true === $this->autoDispatch && null !== ( $alias = ArrayUtils::get( $this->verbAliases, $this->action ) )
        )
        {
            //	A closure?
            if ( !in_array( $alias, Verbs::getDefinedConstants() ) && is_callable( $alias ) )
            {
                $methodToCall = $alias;
            }
        }

        //  Not an alias, build a dispatch method if needed
        if ( !$methodToCall )
        {
            //	If we have a dedicated handler method, call it
            $method = str_ireplace( static::ACTION_TOKEN, $this->action, $this->autoDispatchPattern );

            if ( $this->autoDispatch && method_exists( $this, $method ) )
            {
                $methodToCall = [ $this, $method ];
            }
        }

        if ( $methodToCall )
        {
            $result = call_user_func( $methodToCall );

            //  Only GETs trigger after the call
            if ( Verbs::GET == $this->action )
            {
                $this->triggerActionEvent( $result, null, null, true );
            }

            return $result;
        }

        //	Otherwise just return false
        return false;
    }

    /**
     * @return ServiceResponseInterface
     */
    protected function respond()
    {
        return $this->response;
    }

    /**
     * Sets the request object
     *
     * @param $request ServiceRequestInterface
     *
     * @return $this
     */
    protected function setRequest( ServiceRequestInterface $request )
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Sets the HTTP Action verb
     *
     * @param $action string
     *
     * @return $this
     */
    protected function setAction( $action )
    {
        $this->action = trim( strtoupper( $action ) );

        //	Check verb aliases, set correct action allowing for closures
        if ( null !== ( $alias = ArrayUtils::get( $this->verbAliases, $this->action ) ) )
        {
            //	A closure?
            if ( in_array( $alias, Verbs::getDefinedConstants() ) || !is_callable( $alias ) )
            {
                //	Set original and work with alias
                $this->originalAction = $this->action;
                $this->action = $alias;
            }
        }

        return $this;
    }

    /**
     * @return string The action actually requested
     */
    public function getRequestedAction()
    {
        return $this->originalAction ?: $this->action;
    }

    /**
     * @param string $action
     *
     * @return $this
     */
    public function overrideAction( $action )
    {
        $this->action = trim( strtoupper( $action ) );

        return $this;
    }

    /**
     * @return string
     */
    public function getOriginalAction()
    {
        return $this->originalAction;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Apply the commonly used REST path members to the class.
     *
     * @param string $resourcePath
     *
     * @return $this
     */
    protected function setResourceMembers( $resourcePath = null )
    {
        $this->resourcePath = $resourcePath;
        $this->resourceArray = ( !empty( $this->resourcePath ) ) ? explode( '/', $this->resourcePath ) : [];

        if ( empty( $this->resource ) )
        {
            if ( null !== ( $resource = ArrayUtils::get( $this->resourceArray, 0 ) ) )
            {
                $this->resource = $resource;
            }
        }

        $this->resourceId = ArrayUtils::get( $this->resourceArray, 1 );

        return $this;
    }

    /**
     * Sets the output format of the result.
     *
     * @param int $outputFormat
     */
    protected function setResponseFormat( $outputFormat = null )
    {
        $this->outputFormat = $outputFormat;
    }

    /**
     * @param array      $resources
     * @param array|null $properties
     * @param bool       $wrap
     *
     * @return array
     */
    protected static function makeResourceList( array $resources, $properties = null, $wrap = true )
    {
        $resourceList = [];

        if ( empty( $properties ) || ( is_string( $properties ) && ( 0 === strcasecmp( 'false', $properties ) ) ) )
        {
            $resourceList = array_column( $resources, 'name' );
        }
        elseif ( ( true === $properties ) || ( is_string( $properties ) && ( 0 === strcasecmp( 'true', $properties ) ) ) )
        {
            $resourceList = array_values( $resources );
        }
        else
        {
            foreach ( $resources as $resource )
            {
                if ( is_string( $properties ) )
                {
                    $properties = explode( ',', $properties );
                }

                $resourceList[] = array_intersect_key( $resource, array_flip( $properties ) );
            }
        }

        return ( $wrap ) ? [ "resource" => $resourceList ] : $resourceList;
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return mixed
     */
    protected function getPayloadData( $key = null, $default = null )
    {
        $data = $this->request->getPayloadData( $key, $default );

        return $data;
    }

    /**
     * @param null $key
     * @param null $default
     *
     * @return mixed
     */
    protected function getQueryData( $key = null, $default = null )
    {
        $data = $this->request->query( $key, $default );

        return $data;
    }

    /**
     * @param      $key
     * @param bool $default
     *
     * @return bool
     */
    protected function getQueryBool( $key, $default = false )
    {
        return $this->request->queryBool( $key, $default );
    }

    /**
     * Implement to return the resource configuration for this REST handling object
     *
     * @return array Empty when not implemented, otherwise the array of resource information
     */
    protected function getResources()
    {
        return [ ];
    }

    /**
     * @param mixed $include_properties Use boolean, comma-delimited string, or array of properties
     *
     * @return ServiceResponseInterface
     */
    public function listResources( $include_properties = null )
    {
        $resources = $this->getResources();
        if ( !empty( $resources ) )
        {
            return static::makeResourceList( $resources, $include_properties, true );
        }

        return false;
    }

    /**
     * Handles GET action
     *
     * @return false|ServiceResponseInterface
     */
    protected function handleGET()
    {
        $includeProperties = $this->request->query( 'include_properties' );

        return $this->listResources( $includeProperties );
    }

    /**
     * Handles POST action
     *
     * @return false|ServiceResponseInterface
     */
    protected function handlePOST()
    {
        return false;
    }

    /**
     * Handles PUT action
     *
     * @return false|ServiceResponseInterface
     */
    protected function handlePUT()
    {
        return false;
    }

    /**
     * Handles PATCH action
     *
     * @return false|ServiceResponseInterface
     */
    protected function handlePATCH()
    {
        return false;
    }

    /**
     * Handles DELETE action
     *
     * @return false|ServiceResponseInterface
     */
    protected function handleDELETE()
    {
        return false;
    }

    /**
     * Triggers the appropriate event for the action /service/resource_path.
     *
     */
    protected function triggerActionEvent( &$result, $eventName = null, $event = null, $isPostProcess = false )
    {
        // TODO figure this out for RAVE
    }

    /**
     * @param string $operation
     * @param string $resource
     *
     * @return bool
     */
    public function checkPermission( $operation, $resource = null )
    {
        // TODO figure this out for RAVE
        return true;
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    public function getPermissions( $resource = null )
    {
        // TODO figure this out for RAVE
        return VerbsMask::maskToArray(
            VerbsMask::NONE_MASK | VerbsMask::GET_MASK | VerbsMask::POST_MASK | VerbsMask::PUT_MASK | VerbsMask::PATCH_MASK | VerbsMask::DELETE_MASK
        );
    }
}