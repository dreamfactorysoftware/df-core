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

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Components\RestHandler;
use DreamFactory\Rave\Contracts\ServiceInterface;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Enums\ServiceRequestorTypes;
use DreamFactory\Rave\Enums\VerbsMask;
use DreamFactory\Rave\Events\ServicePostProcess;
use DreamFactory\Rave\Events\ServicePreProcess;
use DreamFactory\Rave\Utility\ResponseFactory;
use DreamFactory\Rave\Utility\Session;

/**
 * Class BaseRestService
 *
 * @package DreamFactory\Rave\Services
 */
class BaseRestService extends RestHandler implements ServiceInterface
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
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire( new ServicePreProcess( $this->name, $this->request, $this->resourcePath ) );
    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {
        $event = new ServicePostProcess( $this->name, $this->request, $this->response, $this->resourcePath );
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire( $event );

        // todo doing something wrong that I have to copy this array back over
        $this->response = $event->response;
    }

    /**
     * @param mixed $fields Use '*', comma-delimited string, or array of properties
     *
     * @return boolean|array
     */
    public function listResources( $fields = null )
    {
        $resources = $this->getResources();
        if ( !empty( $resources ) )
        {
            foreach ($resources as &$resource)
            {
                $resource['access'] = VerbsMask::maskToArray($this->getPermissions(ArrayUtils::get($resource, 'name')));
            }

            return static::makeResourceList( $resources, 'name', $fields, 'resource' );
        }

        return false;
    }

    /**
     * Handles GET action
     *
     * @return mixed
     */
    protected function handleGET()
    {
        $fields = $this->request->getParameter( 'fields' );

        return $this->listResources( $fields );
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

        return ResponseFactory::create( $this->response, $this->nativeFormat );
    }

    /**
     * @param string $operation
     * @param string $resource
     *
     * @return bool
     */
    public function checkPermission( $operation, $resource = null )
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;
        Session::checkServicePermission( $operation, $this->name, $resource, $requestType );
    }

    /**
     * @param string $resource
     *
     * @return string
     */
    public function getPermissions( $resource = null )
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;
        return Session::getServicePermissions($this->name, $resource, $requestType);
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