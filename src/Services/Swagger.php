<?php
namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\StaticCacheable;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\DataFormats;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\Inflector;

/**
 * Swagger
 * API Documentation manager
 *
 */
class Swagger extends BaseRestService
{
    use StaticCacheable;

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
    const SWAGGER_VERSION = '2.0';
    /**
     * @const string The private cache file
     */
    const SWAGGER_CACHE_PREFIX = 'swagger';

    //*************************************************************************
    //	Members
    //*************************************************************************

    protected static $cache_prefix = 'swagger';

    /**
     * @var int|null Native data format of this service - DataFormats enum value.
     */
    protected $nativeFormat = DataFormats::JSON;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return array|string|bool
     */
    protected function handleGET()
    {
        if ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST)) {
            return [];
        }

        if ($this->request->getParameterAsBool(ApiOptions::REFRESH)) {
            $roleId = intval(Session::getRoleId());
            static::clearCache($roleId);
        }

        return $this->getSwagger();
    }

    public static function clearCache($role_id)
    {
        static::removeFromCache(intval($role_id));
    }

    /**
     * Main retrieve point for a list of swagger-able services
     * This builds the full swagger cache if it does not exist
     *
     * @return string The JSON contents of the swagger api listing.
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    public function getSwagger()
    {
        if (Session::isSysAdmin()){
            $roleId = 0;
        } elseif (empty($roleId = Session::getRoleId())) {
            throw new UnauthorizedException("Valid role or administrator required.");
        }

        if (null === ($content = static::getFromCache($roleId))) {
            \Log::info('Building Swagger cache');

            //  Gather the services
            $tags = [];
            $paths = [];
            $definitions = static::getDefaultModels();
            $parameters = ApiOptions::getSwaggerGlobalParameters();

            //  Build services from database
            //  Pull any custom swagger docs
            /** @type Service[] $services */
            $services = Service::all();
            foreach ($services as $service) {
                if (!$service->is_active || !Session::getServicePermissions($service->name)) {
                    continue;
                }

                $name = $service->name;
                $tags[] = ['name' => $name, 'description' => $service->description];
                try {
                    $result = Service::getStoredContentForService($service);
                    if (empty($result)) {
                        throw new NotFoundException("No Swagger content found.");
                    }

                    $servicePaths = (isset($result['paths']) ? $result['paths'] : []);
                    $serviceDefs = (isset($result['definitions']) ? $result['definitions'] : []);

                    $lcName = strtolower($name);
                    $ucwName = Inflector::camelize($name);
                    $pluralName = Inflector::pluralize($name);
                    $pluralUcwName = Inflector::pluralize($ucwName);

                    // replace service placeholders with value for this service instance
                    $servicePaths =
                        str_replace([
                            '{service.name}',
                            '{service.names}',
                            '{service.Name}',
                            '{service.Names}',
                            '{service.label}',
                            '{service.description}'
                        ],
                            [$lcName, $pluralName, $ucwName, $pluralUcwName, $service->label, $service->description],
                            $servicePaths);
                    $serviceDefs =
                        str_replace([
                            '{service.name}',
                            '{service.names}',
                            '{service.Name}',
                            '{service.Names}',
                            '{service.label}',
                            '{service.description}'
                        ],
                            [$lcName, $pluralName, $ucwName, $pluralUcwName, $service->label, $service->description],
                            $serviceDefs);

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
                        'name'  => 'DreamFactory Software, Inc.',
                        'email' => 'support@dreamfactory.com',
                        'url'   => "https://www.dreamfactory.com/"
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
                'parameters'     => $parameters,
                /**
                 * The events thrown that are relevant to Swagger
                 */
                'events'         => [],
            ];

            $content = json_encode($content, JSON_UNESCAPED_SLASHES);

            static::addToCache($roleId, $content, true);

            \Log::info('Swagger cache build process complete');
        }

        return $content;
    }

    public static function getDefaultModels()
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

    public static function getApiDocInfo(Service $service)
    {
        $name = strtolower($service->name);
        $capitalized = Inflector::camelize($service->name);

        return [
            'paths'       => [
                '/' . $name => [
                    'get' =>
                        [
                            'tags'        => [$name],
                            'summary'     => 'get' . $capitalized . '() - Retrieve the Swagger document.',
                            'operationId' => 'get' . $capitalized,
                            'event_name'  => $name . '.retrieve',
                            'parameters'  => [
                                [
                                    'name'        => 'file',
                                    'description' => 'Download the results of the request as a file.',
                                    'type'        => 'string',
                                    'in'          => 'query',
                                    'required'    => false,
                                ],
                            ],
                            'responses'   => [
                                '200'     => [
                                    'description' => 'Swagger Response',
                                    'schema'      => ['$ref' => '#/definitions/SwaggerResponse']
                                ],
                                'default' => [
                                    'description' => 'Error',
                                    'schema'      => ['$ref' => '#/definitions/Error']
                                ]
                            ],
                            'description' => 'This returns the Swagger file containing all API services.',
                        ],
                ],
            ],
            'definitions' => [
                'SwaggerResponse'   => [
                    'type'       => 'object',
                    'properties' => [
                        'apiVersion'  => [
                            'type'        => 'string',
                            'description' => 'Version of the API.',
                        ],
                        'swagger'     => [
                            'type'        => 'string',
                            'description' => 'Version of the Swagger API.',
                        ],
                        'basePath'    => [
                            'type'        => 'string',
                            'description' => 'Base path of the API.',
                        ],
                        'paths'       => [
                            'type'        => 'array',
                            'description' => 'Array of API paths.',
                            'items'       => [
                                '$ref' => '#/definitions/SwaggerPath',
                            ],
                        ],
                        'definitions' => [
                            'type'        => 'array',
                            'description' => 'Array of API definitions.',
                            'items'       => [
                                '$ref' => '#/definitions/SwaggerDefinition',
                            ],
                        ],
                    ],
                ],
                'SwaggerPath'       => [
                    'type'       => 'object',
                    'properties' => [
                        '__name__' => [
                            'type'        => 'string',
                            'description' => 'Path.',
                        ],
                    ],
                ],
                'SwaggerDefinition' => [
                    'type'       => 'object',
                    'properties' => [
                        '__name__' => [
                            'type'        => 'string',
                            'description' => 'Definition.',
                        ],
                    ],
                ],
            ]
        ];
    }
}
