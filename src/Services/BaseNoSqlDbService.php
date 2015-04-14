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

abstract class BaseNoSqlDbService extends BaseDbService
{
    public function getApiDocInfo()
    {
        $_base = parent::getApiDocInfo();

        $_models = [
            'TableSchemas' => [
                'id'         => 'TableSchemas',
                'properties' => [
                    'table' => [
                        'type'        => 'Array',
                        'description' => 'An array of table definitions.',
                        'items'       => [
                            '$ref' => 'TableSchema',
                        ],
                    ],
                ],
            ],
            'TableSchema'  => [
                'id'         => 'TableSchema',
                'properties' => [
                    'name'        => [
                        'type'        => 'string',
                        'description' => 'Identifier/Name for the table.',
                    ],
                    'label'       => [
                        'type'        => 'string',
                        'description' => 'Displayable singular name for the table.',
                    ],
                    'plural'      => [
                        'type'        => 'string',
                        'description' => 'Displayable plural name for the table.',
                    ],
                    'primary_key' => [
                        'type'        => 'string',
                        'description' => 'Field(s), if any, that represent the primary key of each record.',
                    ],
                    'name_field'  => [
                        'type'        => 'string',
                        'description' => 'Field(s), if any, that represent the name of each record.',
                    ],
                    'field'       => [
                        'type'        => 'Array',
                        'description' => 'An array of available fields in each record.',
                        'items'       => [
                            '$ref' => 'FieldSchema',
                        ],
                    ],
                ],
            ],
            'FieldSchema'  => [
                'id'         => 'FieldSchema',
                'properties' => [
                    'name'           => [
                        'type'        => 'string',
                        'description' => 'The API name of the field.',
                    ],
                    'label'          => [
                        'type'        => 'string',
                        'description' => 'The displayable label for the field.',
                    ],
                    'type'           => [
                        'type'        => 'string',
                        'description' => 'The DSP abstract data type for this field.',
                    ],
                    'db_type'        => [
                        'type'        => 'string',
                        'description' => 'The native database type used for this field.',
                    ],
                    'is_primary_key' => [
                        'type'        => 'boolean',
                        'description' => 'Is this field used as/part of the primary key.',
                    ],
                    'validation'     => [
                        'type'        => 'Array',
                        'description' => 'validations to be performed on this field.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                    'value'          => [
                        'type'        => 'Array',
                        'description' => 'Selectable string values for client menus and picklist validation.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        $_base['models'] = array_merge( $_base['models'], $_models );

        return $_base;
    }
}