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

namespace DreamFactory\Rave\Services;

use DreamFactory\Rave\Components\RestHandler;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Utility\ResponseFactory;

/**
 * Class BaseRestService
 *
 * @package DreamFactory\Rave\Services
 */
class BaseRestService extends RestHandler
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var integer|null Database Id of the services entry
     */
    protected $id = null;
    /**
     * @var string Designated type of this service
     */
    protected $type;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return int
     */
    public function getServiceId()
    {
        return $this->id;
    }

    /**
     * @return ServiceResponseInterface
     */
    protected function respond()
    {
        if ( $this->response instanceof ServiceResponseInterface )
        {
            return $this->response;
        }

        return ResponseFactory::create( $this->response, $this->outputFormat, ServiceResponseInterface::HTTP_OK );
    }
}