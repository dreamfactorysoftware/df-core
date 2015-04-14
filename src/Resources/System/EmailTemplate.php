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

class EmailTemplate extends BaseRestSystemResource
{

    public function __construct( $settings = [ ] )
    {
        parent::__construct( $settings );
        $this->model = new \DreamFactory\Rave\Models\EmailTemplate();
    }

    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $name = Inflector::camelize( $this->name );
        $lower = Inflector::camelize( $this->name, null, false, true );

        $_commonProperties = [
            'id'          => [
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Identifier of this ' . $lower . '.',
            ],
            'name'        => [
                'type'        => 'string',
                'description' => 'Displayable name of this ' . $lower . '.',
            ],
            'description' => [
                'type'        => 'string',
                'description' => 'Description of this ' . $lower . '.',
            ],
            'to'          => [
                'type'        => 'array',
                'description' => 'Single or multiple receiver addresses.',
                'items'       => [
                    '$ref' => 'EmailAddress',
                ],
            ],
            'cc'          => [
                'type'        => 'array',
                'description' => 'Optional CC receiver addresses.',
                'items'       => [
                    '$ref' => 'EmailAddress',
                ],
            ],
            'bcc'         => [
                'type'        => 'array',
                'description' => 'Optional BCC receiver addresses.',
                'items'       => [
                    '$ref' => 'EmailAddress',
                ],
            ],
            'subject'     => [
                'type'        => 'string',
                'description' => 'Text only subject line.',
            ],
            'body_text'   => [
                'type'        => 'string',
                'description' => 'Text only version of the body.',
            ],
            'body_html'   => [
                'type'        => 'string',
                'description' => 'Escaped HTML version of the body.',
            ],
            'from'        => [
                'type'        => 'EmailAddress',
                'description' => 'Required sender name and email.',
            ],
            'reply_to'    => [
                'type'        => 'EmailAddress',
                'description' => 'Optional reply to name and email.',
            ],
            'defaults'    => [
                'type'        => 'array',
                'description' => 'Array of default name value pairs for template replacement.',
                'items'       => [
                    'type' => 'string',
                ],
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
                'properties' => $_commonProperties,
            ],
            $name . 'Response' => [
                'id'         => $name . 'Response',
                'properties' => array_merge(
                    $_commonProperties,
                    $_stampProperties
                ),
            ],
            'EmailAddress'           => [
                'id'         => 'EmailAddress',
                'properties' => [
                    'name'  => [
                        'type'        => 'string',
                        'description' => 'Optional name displayed along with the email address.',
                    ],
                    'email' => [
                        'type'        => 'string',
                        'description' => 'Required email address.',
                    ],
                ],
            ],
        ];

        $base['models'] = array_merge( $base['models'], $models );

        return $base;
    }
}