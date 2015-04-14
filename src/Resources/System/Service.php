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

class Service extends BaseRestSystemResource
{

    public function __construct( $settings = [ ] )
    {
        parent::__construct( $settings );
        $this->model = "\\DreamFactory\\Rave\\Models\\Service";
    }

    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $name = Inflector::camelize( $this->name );
        $lower = Inflector::camelize( $this->name, null, false, true );

        $_commonProperties = [
            'id'              => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Identifier of this ' . $lower . '.',
            ],
            'name'            => [
                'type'        => 'string',
                'description' => 'Displayable name of this ' . $lower . '.',
            ],
            'api_name'        => [
                'type'        => 'string',
                'description' => 'Name of the service to use in API transactions.',
            ],
            'description'     => [
                'type'        => 'string',
                'description' => 'Description of this service.',
            ],
            'is_active'       => [
                'type'        => 'boolean',
                'description' => 'True if this service is active for use.',
            ],
            'type'            => [
                'type'        => 'string',
                'description' => 'One of the supported service types.',
                'deprecated'  => true,
            ],
            'type_id'         => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'One of the supported enumerated service types.',
            ],
            'storage_type'    => [
                'type'        => 'string',
                'description' => 'They supported storage service type.',
                'deprecated'  => true,
            ],
            'storage_type_id' => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'One of the supported enumerated storage service types.',
            ],
            'credentials'     => [
                'type'        => 'string',
                'description' => 'Any credentials data required by the service.',
            ],
            'native_format'   => [
                'type'        => 'string',
                'description' => 'The format of the returned data of the service.',
            ],
            'base_url'        => [
                'type'        => 'string',
                'description' => 'The base URL for remote web services.',
            ],
            'parameters'      => [
                'type'        => 'string',
                'description' => 'Additional URL parameters required by the service.',
            ],
            'headers'         => [
                'type'        => 'string',
                'description' => 'Additional headers required by the service.',
            ],
        ];

        $_relatedProperties = [
            'apps'  => [
                'type'        => 'RelatedApps',
                'description' => 'Related apps by app to service assignment.',
            ],
            'roles' => [
                'type'        => 'RelatedRoles',
                'description' => 'Related roles by service to role assignment.',
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
                        'is_system' => [
                            'type'        => 'boolean',
                            'description' => 'True if this service is a default system service.',
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