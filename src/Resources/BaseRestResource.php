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

namespace DreamFactory\Rave\Resources;

use DreamFactory\Rave\Components\RestHandler;
use DreamFactory\Rave\Contracts\RequestHandlerInterface;
use DreamFactory\Rave\Contracts\ResourceInterface;
use DreamFactory\Rave\Events\ResourcePostProcess;
use DreamFactory\Rave\Events\ResourcePreProcess;
use DreamFactory\Rave\Services\BaseRestService;

/**
 * Class BaseRestResource
 *
 * @package DreamFactory\Rave\Resources
 */
class BaseRestResource extends RestHandler implements ResourceInterface
{
    /**
     * @var RestHandler Object that requested this handler, null if this is the Service.
     */
    protected $parent = null;

    /**
     * @return RequestHandlerInterface
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @param RequestHandlerInterface $parent
     */
    public function setParent( RequestHandlerInterface $parent )
    {
        $this->parent = $parent;
    }

    public function getFullPathName( $separator = '/' )
    {
        if ( $this->parent instanceof BaseRestResource )
        {
            return $this->parent->getFullPathName( $separator ) . $separator . $this->name;
        }
        else
        {
            // name of self
            return $this->name;
        }
    }

    public function getServiceName()
    {
        if ( $this->parent instanceof BaseRestService )
        {
            return $this->parent->name;
        }
        elseif ( $this->parent instanceof BaseRestResource )
        {
            return $this->parent->getServiceName();
        }

        return '';
    }

    /**
     * Runs pre process tasks/scripts
     */
    protected function preProcess()
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire(
            new ResourcePreProcess(
                $this->getServiceName(), $this->getFullPathName('.'), $this->request, $this->resourcePath
            )
        );
    }

    /**
     * Runs post process tasks/scripts
     */
    protected function postProcess()
    {
        $event = new ResourcePostProcess(
            $this->getServiceName(), $this->getFullPathName('.'), $this->request, $this->response, $this->resourcePath
        );
        /** @noinspection PhpUnusedLocalVariableInspection */
        $results = \Event::fire( $event );

        // todo doing something wrong that I have to copy this array back over
        $this->response = $event->response;
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();

        /**
         * Some basic apis and models used in DSP REST interfaces
         */

        return [
            'apis'   => [
                [
                    'path'        => $path,
                    'operations'  => [ ],
                    'description' => 'No operations currently defined for this resource.',
                ],
            ],
            'models' => [ ]
        ];
    }
}