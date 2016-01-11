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

    public function getApiDocInfo()
    {
        $serviceName = $this->getServiceName();
        $path = '/' . $serviceName . '/' . $this->getFullPathName();
        $eventPath = $serviceName . '.' . $this->getFullPathName('.');
        $constant = [];

        $constant['paths'] = [
            $path             => [
                'get' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'getConstants() - Retrieve all platform enumerated constants.',
                    'operationId' => 'getConstants',
                    'event_name'  => $eventPath . '.list',
                    'responses'   => [
                        '200'     => [
                            'description' => 'Constants',
                            'schema'      => ['$ref' => '#/definitions/Constants']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Returns an object containing every enumerated type and its constant values',
                ],
            ],
            $path . '/{type}' => [
                'get' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'getConstant() - Retrieve one constant type enumeration.',
                    'operationId' => 'getConstant',
                    'event_name'  => $eventPath . '.read',
                    'parameters'  => [
                        [
                            'name'        => 'type',
                            'description' => 'Identifier of the enumeration type to retrieve.',

                            'type'     => 'string',
                            'in'       => 'path',
                            'required' => true,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Constant',
                            'schema'      => ['$ref' => '#/definitions/Constant']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Returns a constant value.',
                ],
            ],
        ];

        $constant['definitions'] = [
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
        ];

        return $constant;
    }
}