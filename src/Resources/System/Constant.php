<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Utility\ApiDocUtilities;
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
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $constant = [];

        $constant['apis'] = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getConstants() - Retrieve all platform enumerated constants.',
                        'nickname'         => 'getConstants',
                        'type'             => 'Constants',
                        'event_name'       => $eventPath . '.list',
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
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
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'Returns , all fields and no relations are returned.',
                    ],
                ],
                'description' => 'Operations for retrieval individual platform constant enumerations.',
            ],
        ];

        $constant['models'] = [
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

        return $constant;
    }
}