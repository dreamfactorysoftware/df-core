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

    protected function getApiDocSchemas()
    {
        return [
            'ConstantsResponse' => [
                'type'       => 'object',
                'properties' => [
                    'type_name' => [
                        'type'  => 'array',
                        'items' => [
                            '$ref' => '#/components/schemas/ConstantResponse',
                        ],
                    ],
                ],
            ],
            'ConstantResponse'  => [
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
    }
}