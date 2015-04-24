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

use DreamFactory\Rave\Events\Interfaces\EventObserverLike;

/**
 * A basic DSP event for the server-side DSP events
 *
 * This object is modeled after jQuery's event object for ease of client consumption.
 *
 * If an event handler calls an event's stopPropagation() method, no further
 * listeners will be called.
 *
 * ObserverEvent::preventDefault() and ObserverEvent::isDefaultPrevented()
 * are provided in stub form, and do nothing by default. You may implement the
 * response to a "preventDefault" in your services by overriding the methods.
 */
class ObserverEvent extends PlatformEvent
{
    //**************************************************************************
    //* Members
    //**************************************************************************

    /**
     * @var EventObserverLike
     */
    protected $_observer = null;

    //**************************************************************************
    //* Methods
    //**************************************************************************

    /**
     * @param \DreamFactory\Rave\Events\Interfaces\EventObserverLike $observer
     * @param array                                                      $data
     */
    public function __construct( EventObserverLike $observer, $data = array() )
    {
        $this->_observer = $observer;

        parent::__construct( $data );
    }

    /**
     * @return EventObserverLike
     */
    public function getObserver()
    {
        return $this->_observer;
    }

}