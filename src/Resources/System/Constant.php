<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Utility\ResourcesWrapper;

class Constant extends ReadOnlySystemResource
{
    protected function handleGET()
    {
        // todo need some fancy reflection of enum classes in the system we want to expose
        $resources = [];
        if (empty($this->resource)) {
            $resources = [];
        } else {
            switch ($this->resource) {
                default;
                    break;
            }
        }

        return ResourcesWrapper::wrapResources($resources);
    }

    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;

        return [
            'paths' => [
                $path             => [
                    'get' => [
                        'tags'              => [$serviceName],
                        'summary'           => 'get' .
                            $capitalized .
                            '() - Retrieve all platform enumerated constants.',
                        'operationId'       => 'get' . $capitalized . 'Constants',
                        'responses'         => [
                            '200'     => [
                                'description' => 'Constants',
                                'schema'      => ['$ref' => '#/definitions/Constants']
                            ],
                            'default' => [
                                'description' => 'Error',
                                'schema'      => ['$ref' => '#/definitions/Error']
                            ]
                        ],
                        'description'       => 'Returns an object containing every enumerated type and its constant values',
                        'consumes'          => ['application/json', 'application/xml'],
                        'produces'          => ['application/json', 'application/xml'],
                    ],
                ],
                $path . '/{type}' => [
                    'get' => [
                        'tags'              => [$serviceName],
                        'summary'           => 'get' .
                            $capitalized .
                            'Constant() - Retrieve one constant type enumeration.',
                        'operationId'       => 'get' . $capitalized . 'Constant',
                        'consumes'          => ['application/json', 'application/xml'],
                        'produces'          => ['application/json', 'application/xml'],
                        'parameters'        => [
                            [
                                'name'        => 'type',
                                'description' => 'Identifier of the enumeration type to retrieve.',
                                'type'        => 'string',
                                'in'          => 'path',
                                'required'    => true,
                            ],
                        ],
                        'responses'         => [
                            '200'     => [
                                'description' => 'Constant',
                                'schema'      => ['$ref' => '#/definitions/Constant']
                            ],
                            'default' => [
                                'description' => 'Error',
                                'schema'      => ['$ref' => '#/definitions/Error']
                            ]
                        ],
                        'description'       => 'Returns a constant value.',
                    ],
                ],
            ],

            'definitions' => [
                'Constants' => [
                    'type'       => 'object',
                    'properties' => [
                        'type_name' => [
                            'type'  => 'array',
                            'items' => [
                                '$ref' => '#/definitions/Constant',
                            ],
                        ],
                    ],
                ],
                'Constant'  => [
                    'type'       => 'object',
                    'properties' => [
                        'name' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}