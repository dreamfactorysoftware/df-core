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

use DreamFactory\Library\Utility\Inflector;
use DreamFactory\Rave\Resources\BaseRestSystemResource;
use App\User;

class Admin extends BaseRestSystemResource
{
    /**
     * @param array $settings
     */
    public function __construct( $settings = [ ] )
    {
        parent::__construct( $settings );
        $this->model = new User();
    }

    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $name = Inflector::camelize( $this->name );
        $lower = Inflector::camelize( $this->name, null, false, true );

        $_commonProperties = [
            'id'             => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Identifier of this ' . $lower . '.',
            ],
            'email'          => [
                'type'        => 'string',
                'description' => 'The email address required for this user.',
            ],
            'password'       => [
                'type'        => 'string',
                'description' => 'The set-able, but never readable, password.',
            ],
            'first_name'     => [
                'type'        => 'string',
                'description' => 'The first name for this user.',
            ],
            'last_name'      => [
                'type'        => 'string',
                'description' => 'The last name for this user.',
            ],
            'display_name'   => [
                'type'        => 'string',
                'description' => 'Displayable name of this user.',
            ],
            'phone'          => [
                'type'        => 'string',
                'description' => 'Phone number for this user.',
            ],
            'is_active'      => [
                'type'        => 'boolean',
                'description' => 'True if this user is active for use.',
            ],
            'is_sys_admin'   => [
                'type'        => 'boolean',
                'description' => 'True if this user is a system admin.',
            ],
            'default_app_id' => [
                'type'        => 'string',
                'description' => 'The default launched app for this user.',
            ],
            'role_id'        => [
                'type'        => 'string',
                'description' => 'The role to which this user is assigned.',
            ],
        ];

        $_relatedProperties = [
            'default_app' => [
                'type'        => 'RelatedApp',
                'description' => 'Related app by default_app_id.',
            ],
            'role'        => [
                'type'        => 'RelatedRole',
                'description' => 'Related role by role_id.',
            ],
        ];

        $_stampProperties = [
            'created_date'       => [
                'type'        => 'string',
                'description' => 'Date this record was created.',
                'readOnly'    => true,
            ],
            'last_modified_date' => [
                'type'        => 'string',
                'description' => 'Date this record was last modified.',
                'readOnly'    => true,
            ],
        ];

        $models = [
            $name . 'Request'  => [
                'id'         => $name . 'Request',
                'properties' => array_merge(
                    $_commonProperties,
                    $_relatedProperties
                )
            ],
            $name . 'Response' => [
                'id'         => $name . 'Response',
                'properties' => array_merge(
                    $_commonProperties,
                    $_relatedProperties,
                    $_stampProperties,
                    [
                        'last_login_date' => [
                            'type'        => 'string',
                            'description' => 'Timestamp of the last login.',
                        ],
                    ]
                ),
            ],
            'Related' . $name  => [
                'id'         => 'Related' . $name,
                'properties' => array_merge(
                    $_commonProperties,
                    $_stampProperties
                )
            ],
        ];

        $base['models'] = array_merge( $base['models'], $models );

        return $base;
    }
}