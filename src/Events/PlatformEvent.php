<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
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
namespace DreamFactory\Rave\Events;

use Kisma\Core\Events\SeedEvent;
use Kisma\Core\Utility\Option;

/**
 * A basic DSP event for the server-side DSP events
 *
 * This object is modeled after jQuery's event object for ease of client consumption.
 *
 * If an event handler calls an event's stopPropagation() method, no further
 * listeners will be called.
 *
 * PlatformEvent::preventDefault() and PlatformEvent::isDefaultPrevented()
 * are provided in stub form, and do nothing by default. You may implement the
 * response to a "preventDefault" in your services by overriding the methods.
 */
class PlatformEvent extends SeedEvent
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int
     */
    const EVENT_DATA = 0;
    /**
     * @var int
     */
    const REQUEST_DATA = 1;
    /**
     * @var int
     */
    const RESPONSE_DATA = 2;
    /**
     * @type string The default DSP namespace
     */
    const EVENT_NAMESPACE = 'dsp';

    //**************************************************************************
    //* Members
    //**************************************************************************

    /**
     * @var bool Set to true to stop the default action from being performed
     */
    protected $_defaultPrevented = false;
    /**
     * @var bool Indicates that a listener in the chain has changed the data
     */
    protected $_dirty = false;
    /**
     * @var bool If true, this is a post-process type event
     */
    protected $_postProcessScript = false;
    /**
     * @var array|mixed
     */
    protected $_requestData = null;
    /**
     * @var array|mixed
     */
    protected $_responseData = null;

    //**************************************************************************
    //* Methods
    //**************************************************************************

    /**
     * "preventDefault" flag for jQuery compatibility.
     * Unused by server but available for client use
     */
    public function preventDefault()
    {
        $this->_defaultPrevented = true;
    }

    /**
     * "preventDefault" flag for jQuery compatibility.
     * Unused by server but available for client use
     *
     * @return bool
     */
    public function isDefaultPrevented()
    {
        return $this->_defaultPrevented;
    }

    /**
     * Indicates if this event has altered the original state, not including flags
     *
     * @return boolean
     */
    public function isDirty()
    {
        return $this->_dirty;
    }

    /**
     * @param array|PlatformEvent $data
     *
     * @return $this
     */
    public function fromArray( $data = array() )
    {
        foreach ( $data as $_key => $_value )
        {
            //  Event ID cannot be changed
            if ( 'event_id' != $_key )
            {
                Option::set( $this, $_key, $_value );
                $this->_dirty = true;
            }
        }

        //  Special propagation stopper
        if ( ArrayUtils::get( $data, 'stop_propagation', false ) )
        {
            $this->stopPropagation();
        }

        return $this;
    }

    /**
     * {@InheritDoc}
     */
    public function setData( $data, $type = self::EVENT_DATA )
    {
        $this->_dirty = true;

        //  Return a specific data set if requested
        switch ( $type )
        {
            case static::REQUEST_DATA:
                $this->_requestData = $data;
                break;

            case static::RESPONSE_DATA:
                $this->_responseData = $data;
                break;

            default:
                return parent::setData( $data );
        }

        return $this;
    }

    /**
     * Merge an array of data into the $data property
     *
     * @param array|object $data
     * @param int          $type
     *
     * @return $this
     */
    public function mergeData( $data, $type = self::EVENT_DATA )
    {
        foreach ( $data as $_key => $_value )
        {
            switch ( $type )
            {
                case static::REQUEST_DATA:
                    $this->_requestData[ $_key ] = $_value;
                    break;

                case static::RESPONSE_DATA:
                    $this->_responseData[ $_key ] = $_value;
                    break;

                default:
                    $this->_data[ $_key ] = $_value;
                    break;
            }
        }

        return $this;
    }

    /**
     * @return boolean
     */
    public function isPostProcessScript()
    {
        return $this->_postProcessScript;
    }

    /**
     * @param boolean $postProcessScript
     *
     * @return PlatformEvent
     */
    public function setPostProcessScript( $postProcessScript )
    {
        $this->_postProcessScript = $postProcessScript;

        return $this;
    }

    /**
     * @return array|mixed
     */
    public function getRequestData()
    {
        return $this->_requestData;
    }

    /**
     * @param array|mixed $requestData
     *
     * @return PlatformEvent
     */
    public function setRequestData( $requestData )
    {
        $this->_requestData = $requestData;

        return $this;
    }

    /**
     * @return array|mixed
     */
    public function getResponseData()
    {
        return $this->_responseData;
    }

    /**
     * @param array|mixed $responseData
     *
     * @return PlatformEvent
     */
    public function setResponseData( $responseData )
    {
        $this->_responseData = $responseData;

        return $this;
    }

    /**
     * Souped-up event data storage. Allows for general event data (current way it works),
     * request data ($service->_requestPayload), and response data ($service->_response)
     *
     * @param int $which
     *
     * @return array|mixed
     */
    public function getData( $which = self::EVENT_DATA )
    {
        //  Return a specific data set if requested
        switch ( $which )
        {
            case static::REQUEST_DATA:
                return $this->_requestData;

            case static::RESPONSE_DATA:
                return $this->_responseData;
        }

        return parent::getData();
    }
}