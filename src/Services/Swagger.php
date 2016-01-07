<?php
namespace DreamFactory\Core\Services;

use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\ResourcesWrapper;

/**
 * Swagger
 * API Documentation manager
 *
 */
class Swagger extends BaseRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int|null Native data format of this service - DataFormats enum value.
     */
    protected $nativeFormat = DataFormats::JSON;
    /**
     * @const string The current API version
     */
    const API_VERSION = '2.0';
    /**
     * @const string The Swagger version
     */
    const SWAGGER_VERSION = '2.0';
    /**
     * @const string The private cache file
     */
    const SWAGGER_CACHE_FILE = 'swagger.json';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return array|string|bool
     */
    protected function handleGET()
    {
        // lock down access to valid apps only, can't check session permissions
        // here due to sdk access
        // Session::checkAppPermission( null, false );
        if ($this->request->getParameterAsBool('refresh')) {
            static::clearCache();
        }

        return $this->getSwagger();
    }

    public static function clearCache($name = null)
    {
        \Cache::forget(static::SWAGGER_CACHE_FILE);
    }

    /**
     * Main retrieve point for a list of swagger-able services
     * This builds the full swagger cache if it does not exist
     *
     * @return string The JSON contents of the swagger api listing.
     * @throws InternalServerErrorException
     */
    public function getSwagger()
    {
        if (null === ($content = \Cache::get(static::SWAGGER_CACHE_FILE))) {
            \Log::info('Building Swagger cache');

            //  Gather the services
            $tags = [];
            $paths = [];
            $definitions = static::getApiDocDefaultModels();

            //  Build services from database
            //  Pull any custom swagger docs
            /** @type Service[] $services */
            $services = Service::all();
            foreach ($services as $service) {
                $name = $service->name;
                $tags[] = ['name' => $name, 'description' => $service->description];
                // temp
                if ($name != 'system') {
                    continue;
                }
                // temp
                try {
                    $result = Service::getStoredContentForService($service);
                    if (empty($result)) {
                        throw new NotFoundException("No Swagger content found.");
                    }

                    $servicePaths = (isset($result['paths']) ? $result['paths'] : []);
                    $serviceDefs = (isset($result['definitions']) ? $result['definitions'] : []);

                    // replace service placeholders with value for this service instance
                    $servicePaths =
                        str_replace(['{service.name}', '{service.label}', '{service.description}'],
                            [$name, $service->label, $service->description], $servicePaths);
                    $servicePaths =
                        str_replace(['{service.name}', '{service.label}', '{service.description}'],
                            [$name, $service->label, $service->description], $servicePaths);

                    //  Add to the pile
                    $paths = array_merge($paths, $servicePaths);
                    $definitions = array_merge($definitions, $serviceDefs);
                } catch (\Exception $ex) {
                    \Log::error("  * System error creating swagger file for service '$name'.\n{$ex->getMessage()}");
                }

                unset($service);
            }

            // cache main api listing file
            $description = <<<HTML
HTML;

            $content = [
                'swagger'        => static::SWAGGER_VERSION,
                'authorizations' => ['apiKey' => ['type' => 'apiKey', 'passAs' => 'header']],
                'info'           => [
                    'title'       => 'DreamFactory Live API Documentation',
                    'description' => $description,
                    'version'     => \Config::get('df.api_version', static::API_VERSION),
                    //'termsOfServiceUrl' => 'http://www.dreamfactory.com/terms/',
                    'contact'     => [
                        'name'  => 'DreamFactory Support',
                        'email' => 'support@dreamfactory.com',
                        'url'   => "https://www.dreamfactory.com/support"
                    ],
                    'license'     => [
                        'name' => 'Apache 2.0',
                        'url'  => 'http://www.apache.org/licenses/LICENSE-2.0.html'
                    ]
                ],
                //'host'           => 'df.local',
                //'schemes'        => ['https'],
                'basePath'       => '/api/v2',
                'consumes'       => ['application/json'],
                'produces'       => ['application/json'],
                'paths'          => $paths,
                'definitions'    => $definitions,
                'tags'           => $tags,
                /**
                 * The events thrown that are relevant to Swagger
                 */
                'events'         => [],
            ];

            $content = json_encode($content, JSON_UNESCAPED_SLASHES);

            \Cache::forever(static::SWAGGER_CACHE_FILE, $content);

            \Log::info('Swagger cache build process complete');
        }

        return $content;
    }

    public static function getApiDocDefaultModels()
    {
        $wrapper = ResourcesWrapper::getWrapper();

        return [
            'ResourceList' => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of accessible resources available to this service.',
                        'items'       => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
            'Success'      => [
                'type'       => 'object',
                'properties' => [
                    'success' => [
                        'type'        => 'boolean',
                        'description' => 'True when API call was successful, false or error otherwise.',
                    ],
                ],
            ],
            'Error'        => [
                'type'       => 'object',
                'properties' => [
                    'code'    => [
                        'type'        => 'integer',
                        'format'      => 'int32',
                        'description' => 'Error code.',
                    ],
                    'message' => [
                        'type'        => 'string',
                        'description' => 'String description of the error.',
                    ],
                ],
            ],
        ];
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->name;
        $eventPath = $this->name;
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'      => 'GET',
                        'summary'     => 'getApiDocs() - Retrieve the base Swagger document.',
                        'operationId' => 'getApiDocs',
                        'type'        => 'ApiDocsResponse',
                        'event_name'  => $eventPath . '.list',
                        'consumes'    => ['application/json', 'application/xml', 'text/csv'],
                        'produces'    => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'  => [
                            [
                                'name'          => 'file',
                                'description'   => 'Download the results of the request as a file.',

                                'type'          => 'string',
                                'in'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responses'   => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'description' => 'This returns the base Swagger file containing all API services.',
                    ],
                ],
                'description' => 'Operations for retrieving API documents.',
            ],
            [
                'path'        => $path . '/{id}',
                'operations'  => [
                    [
                        'method'      => 'GET',
                        'summary'     => 'getApiDoc() - Retrieve one API document.',
                        'operationId' => 'getApiDoc',
                        'type'        => 'ApiDocResponse',
                        'event_name'  => $eventPath . '.read',
                        'parameters'  => [
                            [
                                'name'          => 'id',
                                'description'   => 'Identifier of the API document to retrieve.',

                                'type'          => 'string',
                                'in'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responses'   => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'description' => '',
                    ],
                ],
                'description' => 'Operations for individual API documents.',
            ],
        ];

        $models = [
            'ApiDocsResponse' => [
                'id'         => 'ApiDocsResponse',
                'properties' => [
                    'apiVersion'     => [
                        'type'        => 'string',
                        'description' => 'Version of the API.',
                    ],
                    'swaggerVersion' => [
                        'type'        => 'string',
                        'description' => 'Version of the Swagger API.',
                    ],
                    'paths'          => [
                        'type'        => 'array',
                        'description' => 'Array of APIs.',
                        'items'       => [
                            '$ref' => 'Api',
                        ],
                    ],
                ],
            ],
            'ApiDocResponse'  => [
                'id'         => 'ApiDocResponse',
                'properties' => [
                    'apiVersion'     => [
                        'type'        => 'string',
                        'description' => 'Version of the API.',
                    ],
                    'swaggerVersion' => [
                        'type'        => 'string',
                        'description' => 'Version of the Swagger API.',
                    ],
                    'basePath'       => [
                        'type'        => 'string',
                        'description' => 'Base path of the API.',
                    ],
                    'paths'          => [
                        'type'        => 'array',
                        'description' => 'Array of APIs.',
                        'items'       => [
                            '$ref' => 'Api',
                        ],
                    ],
                    'definitions'    => [
                        'type'        => 'array',
                        'description' => 'Array of API models.',
                        'items'       => [
                            '$ref' => 'Model',
                        ],
                    ],
                ],
            ],
            'Api'             => [
                'id'         => 'Api',
                'properties' => [
                    'path'        => [
                        'type'        => 'string',
                        'description' => 'Path to access the API.',
                    ],
                    'description' => [
                        'type'        => 'string',
                        'description' => 'Description of the API.',
                    ],
                ],
            ],
            'Model'           => [
                'id'         => 'Model',
                'properties' => [
                    '__name__' => [
                        'type'        => 'string',
                        'description' => 'Model Definition.',
                    ],
                ],
            ],
        ];

        return ['paths' => $apis, 'definitions' => $models];
    }
}
