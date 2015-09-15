<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Utility\ResourcesWrapper;

abstract class BaseNoSqlDbService extends BaseDbService
{
    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();
        $wrapper = ResourcesWrapper::getWrapper();

        $models = [
            'TableSchemas' => [
                'id'         => 'TableSchemas',
                'properties' => [
                    $wrapper => [
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
                    'picklist'          => [
                        'type'        => 'Array',
                        'description' => 'Selectable string values for client menus and picklist validation.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ];

        $base['models'] = array_merge($base['models'], $models);

        return $base;
    }
}