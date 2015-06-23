<?php

namespace DreamFactory\Core\Resources\System;

class Constant extends ReadOnlySystemResource
{
    protected function handleGET()
    {
        // todo need some fancy reflection of enum classes in the system we want to expose
        $resources = [];
        if (empty($this->_resource)) {
            $resources = [];
        } else {
            switch ($this->_resource) {
                default;
                    break;
            }
        }

        return ['resource' => $resources];
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $_constant = [];

        $_constant['apis'] = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getConstants() - Retrieve all platform enumerated constants.',
                        'nickname'         => 'getConstants',
                        'type'             => 'Constants',
                        'event_name'       => $eventPath . '.list',
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
                'path'        => $path . '/{type}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getConstant() - Retrieve one constant type enumeration.',
                        'nickname'         => 'getConstant',
                        'type'             => 'Constant',
                        'event_name'       => $eventPath . '.read',
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