<?php
namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\ApiDocManager;
use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\CacheUtilities;
use DreamFactory\Core\Utility\Session;

/**
 * Swagger
 * DSP API Documentation manager
 *
 */
class Swagger extends BaseRestService implements CachedInterface
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string The current API version
     */
    const API_VERSION = '2.0';
    /**
     * @const string The Swagger version
     */
    const SWAGGER_VERSION = '1.2';
    /**
     * @const string The private cache file
     */
    const SWAGGER_CACHE_FILE = '_.json';
    /**
     * @const integer How long a swagger cache will live, 1440 = 24 minutes (default session timeout).
     */
    const SWAGGER_CACHE_TTL = 1440;

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
//        Session::checkAppPermission( null, false );
        if ($this->request->getParameterAsBool('refresh')) {
            $this->flush();
        }

        if (empty($this->resource)) {
            return $this->getSwagger();
        }

        return $this->getSwaggerForService($this->resource);
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
        $roleId = Session::getRoleId();
        if (null === ($content = CacheUtilities::getByRoleId($roleId, static::SWAGGER_CACHE_FILE))) {
            \Log::info('Building Swagger cache');

            //  Build services from database
            //  Pull any custom swagger docs
            $result = Service::all(['name', 'description']);

            // gather the services
            $services = [];

            //	Spin through services and pull the configs
            foreach ($result as $service) {
                // build main services list
                $services[] = [
                    'path'        => '/' . $service->name,
                    'description' => $service->description
                ];

                unset($service);
            }

            // cache main api listing file
            $description = <<<HTML
HTML;

            $resourceListing = [
                'swaggerVersion' => static::SWAGGER_VERSION,
                'apiVersion'     => \Config::get('df.api_version', static::API_VERSION),
                'authorizations' => ['apiKey' => ['type' => 'apiKey', 'passAs' => 'header']],
                'info'           => [
                    'title'       => 'DreamFactory Live API Documentation',
                    'description' => $description,
                    //'termsOfServiceUrl' => 'http://www.dreamfactory.com/terms/',
                    'contact'     => 'support@dreamfactory.com',
                    'license'     => 'Apache 2.0',
                    'licenseUrl'  => 'http://www.apache.org/licenses/LICENSE-2.0.html'
                ],
                /**
                 * The events thrown that are relevant to Swagger
                 */
                'events'         => [],
            ];

            $content = array_merge($resourceListing, ['apis' => $services]);
            $content = json_encode($content, JSON_UNESCAPED_SLASHES);

            if (false ===
                CacheUtilities::putByRoleId($roleId, static::SWAGGER_CACHE_FILE, $content, static::SWAGGER_CACHE_TTL)
            ) {
                \Log::error('  * System error creating swagger cache file: ' . static::SWAGGER_CACHE_FILE);
            }

            // Add to this services keys for clearing later.
            $key = CacheUtilities::makeKeyFromTypeAndId('role', $roleId, static::SWAGGER_CACHE_FILE);
            CacheUtilities::addKeysByTypeAndId('service', $this->id, $key);

            \Log::info('Swagger cache build process complete');
        }

        return $content;
    }

    /**
     * Main retrieve point for each service
     *
     * @param string $name Which service (name) to retrieve.
     *
     * @return string
     * @throws NotFoundException
     */
    public function getSwaggerForService($name)
    {
        $cachePath = $name . '.json';

        if (null === $content = CacheUtilities::getByServiceId($this->id, $cachePath)) {
            $service = Service::whereName($name)->get()->first();
            if (empty($service)) {
                throw new NotFoundException("Service '$name' not found.");
            }

            $content = [
                'swaggerVersion' => static::SWAGGER_VERSION,
                'apiVersion'     => \Config::get('df.api_version', static::API_VERSION),
                'basePath'       => url('/api/v2'),
            ];

            try {
                $result = ApiDocManager::getStoredContentForService($service);

                if (empty($result)) {
                    throw new NotFoundException("No Swagger content found.");
                }

                $content = array_merge($content, $result);
                $content = json_encode($content, JSON_UNESCAPED_SLASHES);

                // replace service type placeholder with api name for this service instance
                $content = str_replace('{api_name}', $name, $content);

                // cache it for later access
                if (false ===
                    CacheUtilities::putByServiceId($this->id, $cachePath, $content, static::SWAGGER_CACHE_TTL)
                ) {
                    throw new \Exception("  * System error creating swagger cache file.");
                }

                // Add to this the queried service's keys for clearing later.
                $key = CacheUtilities::makeKeyFromTypeAndId('service', $this->id, $cachePath);
                CacheUtilities::addKeysByTypeAndId('service', $service->id, $key);
            } catch (\Exception $ex) {
                \Log::error("  * System error creating swagger file for service '$name'.\n{$ex->getMessage()}");
            }
        }

        return $content;
    }

    /**
     * Clears the cache produced by the swagger annotations
     */
    public function flush()
    {
        CacheUtilities::forgetAllByTypeAndId('service', $this->id);

        ApiDocManager::clearCache();
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
                        'method'           => 'GET',
                        'summary'          => 'getApiDocs() - Retrieve the base Swagger document.',
                        'nickname'         => 'getApiDocs',
                        'type'             => 'ApiDocsResponse',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => ['application/json', 'application/xml', 'text/csv'],
                        'produces'         => ['application/json', 'application/xml', 'text/csv'],
                        'parameters'       => [
                            [
                                'name'          => 'file',
                                'description'   => 'Download the results of the request as a file.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'This returns the base Swagger file containing all API services.',
                    ],
                ],
                'description' => 'Operations for retrieving API documents.',
            ],
            [
                'path'        => $path . '/{id}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getApiDoc() - Retrieve one API document.',
                        'nickname'         => 'getApiDoc',
                        'type'             => 'ApiDocResponse',
                        'event_name'       => $eventPath . '.read',
                        'parameters'       => [
                            [
                                'name'          => 'id',
                                'description'   => 'Identifier of the API document to retrieve.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => '',
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
                    'apis'           => [
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
                    'apis'           => [
                        'type'        => 'array',
                        'description' => 'Array of APIs.',
                        'items'       => [
                            '$ref' => 'Api',
                        ],
                    ],
                    'models'         => [
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

        return ['apis' => $apis, 'models' => $models];
    }
}
