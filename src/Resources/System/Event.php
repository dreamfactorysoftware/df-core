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

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Exceptions\NotFoundException;
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
        EventSubscriber::RESOURCE_NAME          => [
            'name'       => EventSubscriber::RESOURCE_NAME,
            'class_name' => 'DreamFactory\\Rave\\Resources\\System\\EventSubscriber',
            'label'      => 'Subscribers',
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

    // REST service implementation

    /**
     * {@inheritdoc}
     */
    public function listResources( $include_properties = null )
    {
        if ( !$this->request->queryBool( 'as_access_components' ) )
        {
            return parent::listResources( $include_properties );
        }

        $output = [ ];
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
                $output[] = $name;
                $output[] = $name . '*';
            }

            $results = $resource->listResources(false);
            foreach ( $results as $name )
            {
                $name = $className::RESOURCE_NAME . '/' . $name;
                $_access = $this->getPermissions( $name );
                if ( !empty( $_access ) )
                {
                    $output[] = $name;
                }
            }
        }

        return [ 'resource' => $output ];
    }

    /**
     * {@inheritdoc}
     */
//    protected function respond()
//    {
//        if ( Verbs::POST === $this->getRequestedAction() )
//        {
//            switch ( $this->resource )
//            {
//                case Table::RESOURCE_NAME:
//                case Schema::RESOURCE_NAME:
//                    if ( !( $this->response instanceof ServiceResponseInterface ) )
//                    {
//                        $this->response = ResponseFactory::create( $this->response, $this->outputFormat, ServiceResponseInterface::HTTP_CREATED );
//                    }
//                    break;
//            }
//        }
//
//        parent::respond();
//    }

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