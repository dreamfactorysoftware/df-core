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

namespace DreamFactory\Rave\Resources\System;

use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Resources\BaseRestResource;

/**
 * Class Event
 *
 * @package DreamFactory\Rave\Resources
 */
class Event extends BaseRestResource
{
    /**
     * @var array
     */
    protected $resources = [
        EventScript::RESOURCE_NAME           => [
            'name'       => EventScript::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Resources\\System\\EventScript',
            'label'      => 'Scripts',
        ],
        ProcessEvent::RESOURCE_NAME           => [
            'name'       => ProcessEvent::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Resources\\System\\ProcessEvent',
            'label'      => 'Process Events',
        ],
        BroadcastEvent::RESOURCE_NAME           => [
            'name'       => BroadcastEvent::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Resources\\System\\BroadcastEvent',
            'label'      => 'Broadcast Events',
        ],
    ];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return array
     */
    protected function getResources()
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $apis = [ ];
        $models = [ ];
        foreach ($this->resources as $resourceInfo)
        {
            $className = $resourceInfo['class_name'];

            if ( !class_exists( $className ) )
            {
                throw new InternalServerErrorException( 'Service configuration class name lookup failed for resource ' . $this->resourcePath );
            }

            /** @var BaseRestResource $resource */
            $resource = $this->instantiateResource( $className, $resourceInfo );

            $name = $className::RESOURCE_NAME . '/';
            $_access = $this->getPermissions( $name );
            if ( !empty( $_access ) )
            {
                $results = $resource->getApiDocInfo();
                if (isset($results, $results['apis']))
                {
                    $apis = array_merge( $apis, $results['apis'] );
                }
                if (isset($results, $results['models']))
                {
                    $models = array_merge( $models, $results['models'] );
                }
            }
        }

        $base['apis'] = array_merge( $base['apis'], $apis );
        $base['models'] = array_merge( $base['models'], $models );

        return $base;
    }
}