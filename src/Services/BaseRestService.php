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
use DreamFactory\Rave\Events\ServicePostProcess;
use DreamFactory\Rave\Events\ServicePreProcess;
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
     * Runs pre process tasks/scripts
     */
    protected function preProcess()
    {
        $preResults = \Event::fire( new ServicePreProcess( $this->name, $this->action, $this->request, $this->resourcePath ) );
    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {
        $postResults = \Event::fire( new ServicePostProcess( $this->name, $this->action, $this->request, $this->response, $this->resourcePath ) );
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

    public function getApiDocInfo()
    {
        /**
         * Some basic apis and models used in DSP REST interfaces
         */
        return [
            'resourcePath' => '/' . $this->name,
            'produces'     => [ 'application/json', 'application/xml' ],
            'consumes'     => [ 'application/json', 'application/xml' ],
            'apis'         => [
                [
                    'path'        => '/' . $this->name,
                    'operations'  => [],
                    'description' => 'No operations currently defined for this service.',
                ],
            ],
            'models'       => [
                'ComponentList' => [
                    'id'         => 'ComponentList',
                    'properties' => [
                        'resource' => [
                            'type'        => 'Array',
                            'description' => 'Array of accessible components available by this service.',
                            'items'       => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
                'Resource'      => [
                    'id'         => 'Resource',
                    'properties' => [
                        'name' => [
                            'type'        => 'string',
                            'description' => 'Name of the resource.',
                        ],
                    ],
                ],
                'Resources'     => [
                    'id'         => 'Resources',
                    'properties' => [
                        'resource' => [
                            'type'        => 'Array',
                            'description' => 'Array of resources available by this service.',
                            'items'       => [
                                '$ref' => 'Resource',
                            ],
                        ],
                    ],
                ],
                'Success'       => [
                    'id'         => 'Success',
                    'properties' => [
                        'success' => [
                            'type'        => 'boolean',
                            'description' => 'True when API call was successful, false or error otherwise.',
                        ],
                    ],
                ],
            ],
        ];
    }
}