<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\ResourceImport\Manager;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Http\Controllers\StatusController;

class Import extends BaseSystemResource
{

    protected function handleGET()
    {
        return false;
    }

    protected function handlePOST()
    {
        // Get uploaded file
        $file = $this->request->getFile('file', $this->request->getFile('files'));
        // Get the service name. Defaults to 'db' service
        $service = $this->request->input('service', 'db');
        $resource = $this->request->input('resource');

        if(empty($file)){
            $file = $this->request->input('import_url');
        }

        if(!empty($file)){
            $importer = new Manager($file, $service, $resource);
            if($importer->import()){
                $importedResource = $importer->getResource();
                return [
                    'resource' => StatusController::getURI($_SERVER) .
                        '/api/v2/' .
                        $service .
                        '/_table/' .
                        $importedResource
                ];
            }

        } else {
            throw new BadRequestException(
                'No import file supplied. ' .
                'Please upload a file or provide an URL of a file to import. ' .
                'Supported file type(s) is/are ' . implode(', ', Manager::FILE_EXTENSION) . '.'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;

        $apis = [
            $path => [
                'post' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'importData() - Imports resource data.',
                    'operationId' => 'importData',
                    'consumes'    => ['multipart/form-data'],
                    'parameters'  => [
                        [
                            'name'        => 'file',
                            'in'          => 'formData',
                            'type'        => 'file',
                            'description' => 'File to upload'
                        ],
                        [
                            'name'        => 'import_url',
                            'in'          => 'query',
                            'type'        => 'string',
                            'description' => 'URL of the resource file to import'
                        ],
                        [
                            'name'        => 'service',
                            'in'          => 'query',
                            'type'        => 'string',
                            'description' => 'Name of the target service.'
                        ],
                        [
                            'name'        => 'resource',
                            'in'          => 'query',
                            'type'        => 'string',
                            'description' => 'Name of the target resource'
                        ]
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Response',
                            'schema'      => ['$ref' => '#/definitions/' . $class . 'ImportResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' => 'Imports various resource data.'
                ]
            ]
        ];

        $models = [
            $class . 'ImportResponse' => [
                'type'       => 'object',
                'properties' => [
                    'resource' => [
                        'type'        => 'string',
                        'description' => 'URL of the imported resource'
                    ]
                ]
            ]
        ];

        return ['paths' => $apis, 'definitions' => $models];
    }
}