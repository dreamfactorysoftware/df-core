<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Components\Package\Exporter;
use DreamFactory\Core\Components\Package\Importer;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Utility\Packager;
use DreamFactory\Core\Utility\ResponseFactory;
use DreamFactory\Core\Enums\ApiOptions;

class Package extends BaseSystemResource
{
    /** @inheritdoc */
    protected function handleGET()
    {
        $systemOnly = $this->request->getParameterAsBool('system_only');
        $fullTree = $this->request->getParameterAsBool('full_tree');
        $exporter = new Exporter(new \DreamFactory\Core\Components\Package\Package());
        $manifest = $exporter->getManifestOnly($systemOnly, $fullTree);

        return ResponseFactory::create($manifest);
    }

    /** @inheritdoc */
    protected function handlePOST()
    {
        // Get uploaded file
        $file = $this->request->getFile('files');

        // Get file from a url
        if (empty($file)) {
            $file = $this->request->input('import_url');
        }

        if (!empty($file)) {
            //Import
            $extension = strtolower(pathinfo((is_array($file)) ? array_get($file, 'name') : $file, PATHINFO_EXTENSION));
            $statusCode = ServiceResponseInterface::HTTP_OK;

            if ($extension === Packager::FILE_EXTENSION) {
                $package = new Packager($file);
                $result = $package->importAppFromPackage();
            } else {
                $password = $this->request->input('password');
                $overwrite = $this->request->getParameterAsBool('overwrite');
                $package = new \DreamFactory\Core\Components\Package\Package($file, true, $password);
                $importer = new Importer($package, $overwrite);
                $imported = $importer->import();
                $log = $importer->getLog();
                $result = ['success' => $imported, 'log' => $log];
                if (true === $imported) {
                    $statusCode = ServiceResponseInterface::HTTP_CREATED;
                }
            }

            return ResponseFactory::create($result, null, $statusCode);
        } else {
            //Export
            $manifest = $this->request->getPayloadData();
            $package = new \DreamFactory\Core\Components\Package\Package($manifest);
            $exporter = new Exporter($package);
            $url = $exporter->export();
            $public = $exporter->isPublic();
            $result = ['success' => true, 'path' => $url, 'is_public' => $public];

            return ResponseFactory::create($result);
        }
    }

    protected function getApiDocPaths()
    {
        $service = $this->getServiceName();
        $capitalized = camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower($this->name);

        $paths = [
            '/' . $resourceName => [
                'get'  => [
                    'summary'     => 'Retrieves package manifest for all available resources.',
                    'description' => 'Get package manifest only',
                    'operationId' => 'get' . $capitalized . 'ManifestOnly',
                    'parameters'  => [
                        [
                            'name'        => 'system_only',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' => 'Set true to only include system resources in manifest'
                        ],
                        [
                            'name'        => 'full_tree',
                            'schema'      => ['type' => 'boolean'],
                            'in'          => 'query',
                            'description' => 'Set true to include full tree of file service resources'
                        ],
                        ApiOptions::documentOption(ApiOptions::FILE)
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $class . 'Response']
                    ],
                ],
                'post' => [
                    'summary'     => 'Exports or Imports package file.',
                    'description' => 'Export/Import package file',
                    'operationId' => 'importExport' . $capitalized . $class,
                    'parameters'  => [
                        [
                            'name'        => 'import_url',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'URL of the package file to import'
                        ],
                        [
                            'name'        => 'overwrite',
                            'in'          => 'query',
                            'schema'      => ['type' => 'boolean'],
                            'description' => 'Set true to overwrite (PATCH) existing resource during import'
                        ],
                        [
                            'name'        => 'password',
                            'in'          => 'query',
                            'schema'      => ['type' => 'string'],
                            'description' => 'Provide password here if package file is encrypted.'
                        ]
                    ],
                    'requestBody' => [
                        '$ref' => '#/components/requestBodies/' . $class . 'Request'
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $class . 'ExportResponse'],
                        '201' => ['$ref' => '#/components/responses/' . $class . 'ImportResponse'],
                    ],
                ]
            ]
        ];

        return $paths;
    }

    protected function getApiDocRequests()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');

        return [
            $class . 'Request' => [
                'description' => 'Package Import Request',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Manifest']
                    ],
                ],
            ],
        ];
    }

    protected function getApiDocResponses()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');

        return [
            $class . 'Response'       => [
                'description' => 'Package Response',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Manifest']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'Manifest']
                    ],
                ],
            ],
            $class . 'ImportResponse' => [
                'description' => 'Import Response',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'ImportResponse']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'ImportResponse']
                    ],
                ],
            ],
            $class . 'ExportResponse' => [
                'description' => 'Export Response',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'ExportResponse']
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class . 'ExportResponse']
                    ],
                ],
            ],
        ];
    }

    protected function getApiDocSchemas()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');

        $models = [
            $class . 'Manifest'       => [
                'type'       => 'object',
                'properties' => [
                    'version'      => [
                        'type'        => 'string',
                        'description' => 'Package version'
                    ],
                    'df_version'   => [
                        'type'        => 'string',
                        'description' => 'DreamFactory version'
                    ],
                    'secured'      => [
                        'type'        => 'boolean',
                        'description' => 'Flag to indicate whether sensitive data in package is encrypted (true) or not (false) '
                    ],
                    'description'  => [
                        'type'        => 'string',
                        'description' => 'Description of the package'
                    ],
                    'created_date' => [
                        'type'        => 'string',
                        'description' => 'Date/Time when package was created'
                    ],
                    'service'      => [
                        'type'        => 'object',
                        'description' => 'List of all package items. Example: system:{app:[1,2,3]} or s3:[dir, folder/sub-folder]'
                    ]
                ],
            ],
            $class . 'ExportResponse' => [
                'type'       => 'object',
                'properties' => [
                    'success'   => [
                        'type'        => 'boolean',
                        'description' => 'Indicates whether export was successful or not'
                    ],
                    'path'      => [
                        'type'        => 'string',
                        'description' => 'Path (URL) to exported file'
                    ],
                    'is_public' => [
                        'type'        => 'boolean',
                        'description' => 'Indicates whether the URL of exported file is publicly accessible or not.'
                    ]
                ]
            ],
            $class . 'ImportResponse' => [
                'type'       => 'object',
                'properties' => [
                    'success' => [
                        'type'        => 'boolean',
                        'description' => 'Indicates whether import was successful or not'
                    ],
                    'log'     => [
                        'type'        => 'array',
                        'description' => 'Import log',
                        'items'       => [
                            'type'        => 'array',
                            'description' => 'log level',
                            'items'       => [
                                'type' => 'string'
                            ]
                        ]
                    ]
                ]
            ],
        ];

        return $models;
    }
}