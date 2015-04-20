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
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
use DreamFactory\Rave\Resources\BaseRestResource;
use DreamFactory\Rave\Models\SystemResource;
use DreamFactory\Rave\Utility\ApiDocUtilities;

class SystemManager extends BaseRestService
{
    /**
     * @return array
     */
    protected function getResources()
    {
        return SystemResource::all()->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $apis = [
            [
                'path'        => '/' . $this->name,
                'description' => 'Operations available for database tables.',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getResourceList() - List all resource names.',
                        'nickname'         => 'getResourceList',
                        'notes'            => 'List the resource names available in this service.',
                        'type'             => 'ComponentList',
                        'event_name'       => [ $this->name . '.list' ],
                        'parameters'       => [
                            [
                                'name'          => 'include_properties',
                                'description'   => 'Return other properties available for each resource.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => true,
                                'default'       => true,
                            ],
                            [
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the resource list.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses( [ 400, 401, 500 ] ),
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'getResources() - List all resources.',
                        'nickname'         => 'getResources',
                        'notes'            => 'List the resources available on this service. ',
                        'type'             => 'Resources',
                        'event_name'       => [ $this->name . '.list' ],
                        'parameters'       => [
                            [
                                'name'          => 'include_properties',
                                'description'   => 'Return other properties available for each resource.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => true,
                                'default'       => true,
                            ],
                            [
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the resource list.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses( [ 400, 401, 500 ] ),
                    ],
                    [
                        'method'           => 'GET',
                        'summary'          => 'getAccessComponents() - List all role accessible components.',
                        'nickname'         => 'getAccessComponents',
                        'notes'            => 'List the names of all the role accessible components.',
                        'type'             => 'ComponentList',
                        'event_name'       => [ $this->name . '.list' ],
                        'parameters'       => [
                            [
                                'name'          => 'as_access_components',
                                'description'   => 'Return the names of all the accessible components.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => true,
                                'default'       => true,
                            ],
                            [
                                'name'          => 'refresh',
                                'description'   => 'Refresh any cached copy of the resource list.',
                                'allowMultiple' => false,
                                'type'          => 'boolean',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses( [ 400, 401, 500 ] ),
                    ],
                ],
            ],
        ];

        $models = [ ];

        foreach ( $this->getResources() as $resourceInfo )
        {
            $className = ArrayUtils::get( $resourceInfo, 'class_name' );

            if ( !class_exists( $className ) )
            {
                throw new InternalServerErrorException( 'Service configuration class name lookup failed for resource ' . $this->resourcePath );
            }

            /** @var BaseRestResource $resource */
            $resource = $this->instantiateResource( $className, $resourceInfo );

            $name = ArrayUtils::get( $resourceInfo, 'name', '' ) . '/';
            $_access = $this->getPermissions( $name );
            if ( !empty( $_access ) )
            {
                $results = $resource->getApiDocInfo();
                if ( isset( $results, $results['apis'] ) )
                {
                    $apis = array_merge( $apis, $results['apis'] );
                }
                if ( isset( $results, $results['models'] ) )
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