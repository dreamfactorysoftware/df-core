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

use DreamFactory\Rave\Models\ServiceType;
use DreamFactory\Rave\Resources\BaseRestSystemResource;

class Constant extends BaseRestSystemResource
{

    public function __construct( $settings = [ ] )
    {
        parent::__construct( $settings );
    }

    protected function handleGET()
    {
        $resources = [ ];
        if ( empty( $this->_resourceId ) )
        {
            $resources = [
                [ 'name' => 'service_type', 'label' => 'Service Type' ],
            ];
        }
        else
        {
            switch ( $this->_resourceId )
            {
                case 'service_type':
                    $services = ServiceType::all()->toArray();
                    $resources = $this->makeResourceList( $services, null, false );
                    break;
                default;
                    break;
            }
        }

        return [ 'resource' => $resources ];
    }

    protected function handlePOST()
    {
        return false;
    }

    public function getApiDocInfo()
    {
        $_constant = [ ];

        $_constant['apis'] = [
            [
                'path'        => '/{api_name}/constant',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getConstants() - Retrieve all platform enumerated constants.',
                        'nickname'         => 'getConstants',
                        'type'             => 'Constants',
                        'event_name'       => '{api_name}.constants.list',
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'Returns an object containing every enumerated type and its constant values',
                    ],
                ],
                'description' => 'Operations for retrieving platform constants.',
            ],
            [
                'path'        => '/{api_name}/constant/{type}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getConstant() - Retrieve one constant type enumeration.',
                        'nickname'         => 'getConstant',
                        'type'             => 'Constant',
                        'event_name'       => '{api_name}.constant.read',
                        'parameters'       => [
                            [
                                'name'          => 'type',
                                'description'   => 'Identifier of the enumeration type to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'Returns , all fields and no relations are returned.',
                    ],
                ],
                'description' => 'Operations for retrieval individual platform constant enumerations.',
            ],
        ];

        $_constant['models'] = [
            'Constants' => [
                'id'         => 'Constants',
                'properties' => [
                    'type_name' => [
                        'type'  => 'array',
                        'items' => [
                            '$ref' => 'Constant',
                        ],
                    ],
                ],
            ],
            'Constant'  => [
                'id'         => 'Constant',
                'properties' => [
                    'name' => [
                        'type'  => 'array',
                        'items' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        return $_constant;
    }
}