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

use DreamFactory\Rave\Resources\BaseRestSystemResource;

class Setting extends BaseRestSystemResource
{

    public function __construct( $settings = [ ] )
    {
        parent::__construct( $settings );
        $this->model = new \DreamFactory\Rave\Models\Setting();
    }

    public function getApiDocInfo()
    {
        $apis = [
            [
                'path'        => '/{api_name}/setting',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getSettings() - Retrieve all custom system settings.',
                        'nickname'         => 'getSettings',
                        'type'             => 'Settings',
                        'event_name'       => '{api_name}.settings.read',
                        'responseMessages' => [
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'Returns an object containing name-value pairs for custom system settings',
                    ],
                    [
                        'method'           => 'POST',
                        'summary'          => 'setSettings() - Update one or more custom system settings.',
                        'nickname'         => 'setSettings',
                        'type'             => 'Success',
                        'event_name'       => '{api_name}.settings.update',
                        'parameters'       => [
                            [
                                'name'          => 'body',
                                'description'   => 'Data containing name-value pairs of desired settings.',
                                'allowMultiple' => false,
                                'type'          => 'Settings',
                                'paramType'     => 'body',
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
                        'notes'            =>
                            'A valid session and system configuration permissions is required to edit settings. ' .
                            'Post body should be an array of name-value pairs.',
                    ],
                ],
                'description' => 'Operations for managing custom system settings.',
            ],
            [
                'path'        => '/{api_name}/setting/{setting_name}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getSetting() - Retrieve one custom system setting.',
                        'nickname'         => 'getSetting',
                        'type'             => 'Setting',
                        'event_name'       => '{api_name}.setting.read',
                        'parameters'       => [
                            [
                                'name'          => 'setting_name',
                                'description'   => 'Name of the setting to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'Setting will be returned as an object containing name-value pair.',
                    ],
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteSetting() - Delete one custom setting.',
                        'nickname'         => 'deleteSetting',
                        'type'             => 'Success',
                        'event_name'       => '{api_name}.setting.delete',
                        'parameters'       => [
                            [
                                'name'          => 'setting',
                                'description'   => 'Name of the setting to delete.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => [
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'Denied Access - No permission.',
                                'code'    => 403,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'A valid session with system configuration permissions is required to delete settings.',
                    ],
                ],
                'description' => 'Operations for individual custom system settings.',
            ],
        ];

        $models = [
            'Settings' => [
                'id'         => 'Settings',
                'properties' => [
                    'type_name' => [
                        'type'  => 'array',
                        'items' => [
                            '$ref' => 'Setting',
                        ],
                    ],
                ],
            ],
            'Setting'  => [
                'id'         => 'Setting',
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

        return [ 'apis' => $apis, 'models' => $models];

    }
}