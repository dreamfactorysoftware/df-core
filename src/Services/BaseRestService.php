<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\ServiceCacheable;
use DreamFactory\Core\Contracts\CacheInterface;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Enums\ApiDocFormatTypes;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\ServiceDoc;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session;
use Log;
use ServiceManager as ServiceMgr;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BaseRestService
 *
 * @package DreamFactory\Core\Services
 */
class BaseRestService extends RestHandler implements ServiceInterface, CacheInterface
{
    use ServiceCacheable;

    const RESOURCE_IDENTIFIER = 'name';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /** @type array Service Resources */
    protected static $resources = [];
    /**
     * @var integer|null Database Id of the services entry
     */
    protected $id = null;
    /**
     * @var string Designated type of this service
     */
    protected $type;
    /**
     * @var boolean Is this service activated for use?
     */
    protected $isActive = false;
    /**
     * @var array Holder for various configuration options
     */
    protected $config = [];
    /**
     * @var array Holder for various API doc options
     */
    protected $doc = [];
    /**
     * @type string
     */
    protected $configCachePrefix = '';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        //  Most services have a config section that may include lookups
        $this->config = (array)array_get($settings, 'config', []);
        //  Replace any private lookups
        Session::replaceLookups($this->config, true);

        $this->setCachePrefix('service_' . $this->id . ':');
    }

    /**
     * @return int
     */
    public function getServiceId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return ServiceTypeInterface
     */
    public function getServiceTypeInfo()
    {
        if (null !== $typeInfo = ServiceMgr::getServiceType($this->type)) {
            return $typeInfo;
        }

        return null;
    }

    /**
     * @return boolean
     */
    public function isActive()
    {
        return $this->isActive;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(ServiceRequestInterface $request, $resource = null)
    {
        Log::info('[REQUEST]', [
            'API Version' => $request->getApiVersion(),
            'Method'      => $request->getMethod(),
            'Service'     => $this->name,
            'Resource'    => $resource,
            'Requestor'   => $request->getRequestorType(),
        ]);

        Log::debug('[REQUEST]', [
            'Parameters' => json_encode($request->getParameters(), JSON_UNESCAPED_SLASHES),
            'API Key'    => $request->getHeader('X_DREAMFACTORY_API_KEY'),
            'JWT'        => $request->getHeader('X_DREAMFACTORY_SESSION_TOKEN')
        ]);

        if (!$this->isActive) {
            throw new ForbiddenException("Service {$this->name} is deactivated.");
        }

        $response = parent::handleRequest($request, $resource);
        if ($response instanceof RedirectResponse) {
            Log::info('[RESPONSE] Redirect', ['Status Code' => $response->getStatusCode()]);
            Log::debug('[RESPONSE]', ['Target URL' => $response->getTargetUrl()]);
        } elseif ($response instanceof StreamedResponse) {
            Log::info('[RESPONSE] Stream', ['Status Code' => $response->getStatusCode()]);
        } else {
            Log::info('[RESPONSE]', ['Status Code' => $response->getStatusCode(), 'Content-Type' => $response->getContentType()]);
        }

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function getResources()
    {
        return array_values($this->getResourceHandlers());
    }

    protected function getResourceHandlers()
    {
        return static::$resources;
    }

    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return static::RESOURCE_IDENTIFIER;
    }

    /**
     * {@inheritdoc}
     */
    public function checkPermission($operation, $resource = null)
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;
        Session::checkServicePermission($operation, $this->name, $resource, $requestType);
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissions($resource = null)
    {
        $requestType = ($this->request) ? $this->request->getRequestorType() : ServiceRequestorTypes::API;

        return Session::getServicePermissions($this->name, $resource, $requestType);
    }

    public function getAccessList()
    {
        if (!empty($this->getPermissions())) {
            return ['', '*'];
        }

        return [];
    }

    /**
     * {@inheritdoc}
     */
    protected function handleGET()
    {
        if ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST)) {
            return ResourcesWrapper::wrapResources($this->getAccessList());
        }

        return parent::handleGET();
    }

    public function getEventMap()
    {
        $content = $this->getApiDoc();
        $accessList = [];
        try {
            $accessList = $this->getAccessList();
        } catch (\Exception $ex) {
            // possibly misconfigured service, don't propagate
            \Log::warning("Service {$this->name} failed to get access list. " . $ex->getMessage());
        }

        $map = $this->parseSwaggerEvents($content, $accessList);

        // check children for any extras
        try {
            foreach ($this->getResourceHandlers() as $resourceInfo) {
                $className = array_get($resourceInfo, 'class_name');
                if (!class_exists($className)) {
                    throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                        $this->resourcePath);
                }

                /** @var BaseRestResource $resource */
                $resource = $this->instantiateResource($className, $resourceInfo);
                $results = $resource->getEventMap();
                $map = array_merge($map, $results);
            }
        } catch (\Exception $ex) {
            // carry on
        }
        ksort($map, SORT_NATURAL);

        return $map;
    }

    public static function storedContentToArray($content, $format, $service_info = [])
    {
        // replace service placeholders with value for this service instance
        if (!empty($name = data_get($service_info, 'name'))) {
            $lcName = strtolower($name);
            $ucwName = camelize($name);
            $pluralName = str_plural($name);
            $pluralUcwName = str_plural($ucwName);

            $content = str_replace(
                ['{service.name}', '{service.names}', '{service.Name}', '{service.Names}'],
                [$lcName, $pluralName, $ucwName, $pluralUcwName],
                $content);
        }
        if (!empty($label = data_get($service_info, 'label'))) {
            $content = str_replace('{service.label}', $label, $content);
        }
        if (!empty($description = data_get($service_info, 'description'))) {
            $content = str_replace('{service.description}', $description, $content);
        }

        switch ($format) {
            case ApiDocFormatTypes::SWAGGER_JSON:
                $content = json_decode($content, true);
                break;
            case ApiDocFormatTypes::SWAGGER_YAML:
                $content = Yaml::parse($content);
                break;
            default:
                throw new InternalServerErrorException("Invalid API Doc Format '$format'.");
        }

        return $content;
    }

    /**
     * @param array $content
     * @param array $access
     *
     * @return array
     */
    protected function parseSwaggerEvents(array $content, array $access = [])
    {
        $events = [];
        $eventCount = 0;

        foreach (array_get($content, 'paths', []) as $path => $api) {
            $apiEvents = [];
            $apiParameters = [];
            $pathParameters = [];

            $path = trim($path, '/');
            $eventPath = $this->name;
            if (!empty($path)) {
                $eventPath .= '.' . str_replace('/', '.', $path);
            }
            $replacePos = strpos($path, '{');

            foreach ($api as $ixOps => $operation) {
                if ('parameters' === $ixOps) {
                    $pathParameters = $operation;
                    continue;
                }

                $method = strtolower($ixOps);
                if (!array_search("$eventPath.$method", $apiEvents)) {
                    $apiEvents[] = "$eventPath.$method";
                    $eventCount++;
                    $parameters = array_get($operation, 'parameters', []);
                    if (!empty($pathParameters)) {
                        $parameters = array_merge($pathParameters, $parameters);
                    }
                    foreach ($parameters as $parameter) {
                        $type = array_get($parameter, 'in', '');
                        if ('path' === $type) {
                            $name = array_get($parameter, 'name', '');
                            $options = array_get($parameter, 'enum', array_get($parameter, 'options'));
                            if (empty($options) && !empty($access) && (false !== $replacePos)) {
                                $checkFirstOption = strstr(substr($path, $replacePos + 1), '}', true);
                                if ($name !== $checkFirstOption) {
                                    continue;
                                }
                                $options = [];
                                // try to match any access path
                                foreach ($access as $accessPath) {
                                    $accessPath = rtrim($accessPath, '/*');
                                    if (!empty($accessPath) && (strlen($accessPath) > $replacePos)) {
                                        if (0 === substr_compare($accessPath, $path, 0, $replacePos)) {
                                            $option = substr($accessPath, $replacePos);
                                            if (false !== strpos($option, '/')) {
                                                $option = strstr($option, '/', true);
                                            }
                                            $options[] = $option;
                                        }
                                    }
                                }
                            }
                            if (!empty($options)) {
                                $apiParameters[$name] = array_values(array_unique($options));
                            }
                        }
                    }
                }

                unset($operation);
            }

            $events[$eventPath]['type'] = 'api';
            $events[$eventPath]['endpoints'] = $apiEvents;
            $events[$eventPath]['parameter'] = (empty($apiParameters)) ? null : $apiParameters;

            unset($apiEvents, $apiParameters, $api);
        }

        \Log::debug("  * Discovered $eventCount event(s) for service {$this->name}.");

        return $events;
    }

    /**
     * @param null|string|int      $key
     * @param null|string|bool|int $default
     *
     * @return array
     */
    public function getConfig($key = null, $default = null)
    {
        if (!is_array($this->config) || empty($this->config)) {
            return [];
        }

        if (empty($key)) {
            return $this->config;
        }

        return array_get($this->config, $key, $default);
    }

    public function getApiDoc($refresh = false)
    {
        $cacheKey = 'service_doc';
        $id = $this->id;
        if ($refresh) {
            $this->removeFromCache($cacheKey);
        }

        return $this->rememberCacheForever($cacheKey, function () use ($id) {
            if ($doc = ServiceDoc::whereServiceId($id)->first()) {
                if (!empty($content = array_get($doc, 'content'))) {
                    if (is_string($content)) {
                        // need to convert to array format for handling
                        $info = [
                            'name'        => $this->name,
                            'label'       => $this->label,
                            'description' => $this->description,
                        ];

                        $content = $this->storedContentToArray($content, array_get($doc, 'format'), $info);
                    }
                }
            }

            if (empty($content)) {
                $content = $this->getApiDocInfo();
            }

            return (array)$content;
        });
    }

    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();
        foreach ($this->getResourceHandlers() as $resourceInfo) {
            $className = array_get($resourceInfo, 'class_name');
            if (!class_exists($className)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $className);
            }

            /** @var BaseRestResource $resource */
            $resource = $this->instantiateResource($className, $resourceInfo);
            $content = $resource->getApiDocInfo();
            if (isset($content['paths'])) {
                $base['paths'] = array_merge((array)array_get($base, 'paths'), (array)$content['paths']);
            }
            if (isset($content['components'])) {
                if (isset($content['components']['requestBodies'])) {
                    $base['components']['requestBodies'] = array_merge((array)array_get($base,
                        'components.requestBodies'),
                        (array)$content['components']['requestBodies']);
                }
                if (isset($content['components']['responses'])) {
                    $base['components']['responses'] = array_merge((array)array_get($base, 'components.responses'),
                        (array)$content['components']['responses']);
                }
                if (isset($content['components']['schemas'])) {
                    $base['components']['schemas'] = array_merge((array)array_get($base, 'components.schemas'),
                        (array)$content['components']['schemas']);
                }
            }
        }

        return $base;
    }

    protected function getApiDocPaths()
    {
        $capitalized = camelize($this->name);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);

        $paths = [
            '/' => [
//                    'summary'     => '',
//                    'description' => '',
                'get' => [
                    'summary'     => 'Get resources for this service.',
                    'description' => 'Return an array of the resources available.',
                    'operationId' => 'get' . $capitalized . 'Resources',
                    'parameters'  => [
                        ApiOptions::documentOption(ApiOptions::AS_LIST),
                        ApiOptions::documentOption(ApiOptions::AS_ACCESS_LIST),
                        ApiOptions::documentOption(ApiOptions::INCLUDE_ACCESS),
                        ApiOptions::documentOption(ApiOptions::FIELDS),
                        ApiOptions::documentOption(ApiOptions::ID_FIELD),
                        ApiOptions::documentOption(ApiOptions::ID_TYPE),
                        ApiOptions::documentOption(ApiOptions::REFRESH),
                    ],
                    'responses'   => [
                        '200' => ['$ref' => '#/components/responses/' . $pluralClass . 'Response']
                    ],
                ],
            ],
        ];

        return $paths;
    }

    protected function getApiDocResponses()
    {
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);

        return [
            $class . 'Response'       => [
                'description' => 'Resource List',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class]
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $class]
                    ],
                ],
            ],
            $pluralClass . 'Response' => [
                'description' => 'Resource List',
                'content'     => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/' . $pluralClass]
                    ],
                    'application/xml'  => [
                        'schema' => ['$ref' => '#/components/schemas/' . $pluralClass]
                    ],
                ],
            ],
        ];
    }

    protected function getApiDocSchemas()
    {
        $wrapper = ResourcesWrapper::getWrapper();
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);

        return [
            $class       => [
                'type'       => 'object',
                'properties' => [
                    static::getResourceIdentifier() => [
                        'type'        => 'string',
                        'description' => 'Identifier of the resource.',
                    ],
                ],
            ],
            $pluralClass => [
                'type'       => 'object',
                'properties' => [
                    $wrapper => [
                        'type'        => 'array',
                        'description' => 'Array of resources available to this service.',
                        'items'       => [
                            '$ref' => '#/components/schemas/' . $class,
                        ],
                    ],
                ],
            ],
        ];
    }
}