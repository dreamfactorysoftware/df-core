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

/**
 * Contains additional information about the REST service call triggering the event
 */
class PlatformServiceEvent extends PlatformEvent
{

    //**************************************************************************
    //* Members
    //**************************************************************************

    /**
     * @var string The service called
     */
    protected $_apiName = null;
    /**
     * @var string The requested resource
     */
    protected $_resource = false;

    //**************************************************************************
    //* Methods
    //**************************************************************************

    /**
     * @param string $apiName
     * @param string $resource
     * @param array  $data
     */
    public function __construct( $apiName, $resource, $data = null )
    {
        $this->_apiName = $apiName;
        $this->_resource = $resource;

        parent::__construct( $data );
    }

    /**
     * @return string
     */
    public function getApiName()
    {
        return $this->_apiName;
    }

    /**
     * @return string
     */
    public function getResource()
    {
        return $this->_resource;
    }
}