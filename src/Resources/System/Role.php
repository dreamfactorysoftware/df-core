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

class Role extends BaseRestSystemResource
{
    public function __construct( $settings = [ ] )
    {
        parent::__construct( $settings );
        $this->model = new \DreamFactory\Rave\Models\Role();
    }

    public function getApiDocInfoz()
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
            'name'           => [
                'type'        => 'string',
                'description' => 'Displayable name of this ' . $lower . '.',
            ],
            'description'    => [
                'type'        => 'string',
                'description' => 'Description of this ' . $lower . '.',
            ],
            'is_active'      => [
                'type'        => 'boolean',
                'description' => 'Is this ' . $lower . ' active for use.',
            ],
            'default_app_id' => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Default launched app for this ' . $lower . '.',
            ],
        ];

        $_relatedProperties = [
            'default_app' => [
                'type'        => 'RelatedApp',
                'description' => 'Related app by default_app_id.',
            ],
            'users'       => [
                'type'        => 'RelatedUsers',
                'description' => 'Related users by User.role_id.',
            ],
            'apps'        => [
                'type'        => 'RelatedApps',
                'description' => 'Related apps by role assignment.',
            ],
            'services'    => [
                'type'        => 'RelatedServices',
                'description' => 'Related services by role assignment.',
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
                    $_stampProperties
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