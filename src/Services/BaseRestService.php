<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\ServiceCacheable;
use DreamFactory\Core\Contracts\CacheInterface;
use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Enums\ApiDocFormatTypes;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\Verbs;
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

        // Intercept _spec resource requests before normal resource routing
        if ($resource === '_spec' && $request->getMethod() === Verbs::GET) {
            return $this->handleSpecResource($request);
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

    /**
     * Handle GET /api/v2/{service}/_spec requests.
     *
     * Returns the full OpenAPI 3.0.0 specification for this service.
     *
     * Query parameters:
     *   format=json|yaml    - Output format (default: json)
     *   compact=true        - Token-efficient summary (paths + params only, no schemas)
     *   resource_name={name} - Filter to only paths involving this resource
     *   tables=true         - Include actual table/resource names (database services)
     *   refresh=true        - Bypass cache
     *
     * @param ServiceRequestInterface $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSpecResource(ServiceRequestInterface $request)
    {
        $this->setRequest($request);
        $this->checkPermission(Verbs::GET, '_spec');

        $refresh = $request->getParameterAsBool(ApiOptions::REFRESH);
        $doc = $this->getApiDoc($refresh);

        $compact = $request->getParameterAsBool('compact');
        $resourceFilter = $request->getParameter('resource_name', '');
        $includeTables = $request->getParameterAsBool('tables');
        $modelMode = $request->getParameterAsBool('model');
        $stockMode = $request->getParameterAsBool('stock');

        // --- Feature: ?model=true — LLM-optimized condensed data model ---
        if ($modelMode) {
            return $this->handleModelResponse($request, $refresh, $stockMode);
        }

        // Build full OpenAPI 3.0 envelope
        $spec = [
            'openapi' => '3.0.0',
            'info'    => [
                'title'       => $this->label ?: $this->name,
                'description' => (string)$this->description,
                'version'     => config('df.api_version', '2.0'),
            ],
            'servers' => [
                [
                    'url'         => '/api/v2/' . $this->name,
                    'description' => 'DreamFactory API - ' . ($this->label ?: $this->name),
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'SessionTokenHeader' => [
                        'type'        => 'apiKey',
                        'in'          => 'header',
                        'name'        => 'X-DreamFactory-Session-Token',
                        'description' => 'JWT session token from /api/v2/system/admin/session or /api/v2/user/session',
                    ],
                    'ApiKeyHeader' => [
                        'type'        => 'apiKey',
                        'in'          => 'header',
                        'name'        => 'X-DreamFactory-API-Key',
                        'description' => 'Application API key',
                    ],
                ],
            ],
            'security' => [
                ['SessionTokenHeader' => []],
                ['ApiKeyHeader' => []],
            ],
            'paths' => [],
            'tags'  => [
                [
                    'name'        => $this->name,
                    'description' => (string)$this->description,
                ],
            ],
        ];

        // Merge paths from service doc
        if (isset($doc['paths'])) {
            foreach ($doc['paths'] as $path => $pathInfo) {
                $filtered = [];
                foreach ($pathInfo as $verb => $verbInfo) {
                    if (!isset($verbInfo['tags'])) {
                        $verbInfo['tags'] = [$this->name];
                    }
                    $filtered[strtolower($verb)] = $verbInfo;
                }
                if (!empty($filtered)) {
                    $spec['paths'][$path] = $filtered;
                }
            }
        }

        // Merge components (schemas, parameters, requestBodies, responses)
        foreach (['schemas', 'parameters', 'requestBodies', 'responses'] as $type) {
            if (isset($doc['components'][$type])) {
                $spec['components'][$type] = array_merge(
                    $spec['components'][$type] ?? [],
                    $doc['components'][$type]
                );
            }
            if (isset($doc[$type])) {
                $spec['components'][$type] = array_merge(
                    $spec['components'][$type] ?? [],
                    $doc[$type]
                );
            }
        }

        // --- Feature: Error response schemas (#3) ---
        $this->injectErrorSchemas($spec);

        // --- Feature: Natural language descriptions (#4) ---
        $this->injectNaturalLanguageDescriptions($spec);

        // --- Feature: Examples (#1) ---
        $this->injectExamples($spec);

        // --- Feature: ?resource_name={name} filter ---
        if (!empty($resourceFilter)) {
            $this->filterSpecByResource($spec, $resourceFilter);
        }

        // --- Feature: ?tables=true — resource list for database services (#2) ---
        if ($includeTables || !$compact) {
            $this->injectResourceList($spec, $includeTables);
        }

        // --- Feature: Relationship metadata for database services (#6) ---
        $this->injectRelationships($spec);

        // --- Feature: Rate limit hints (#7) ---
        $this->injectRateLimits($spec);

        // --- Feature: LLM usage hints ---
        $spec['x-dreamfactory-usage'] = $this->buildUsageHints();

        // --- Feature: ?compact=true ---
        if ($compact) {
            $spec = $this->compactSpec($spec);
        }

        return $this->formatSpecResponse($spec, $request);
    }

    /**
     * Filter spec paths to only those involving a specific resource.
     * Also prunes schemas to only those referenced by the remaining paths.
     */
    private function filterSpecByResource(array &$spec, string $resource): void
    {
        $lcResource = strtolower($resource);
        $filteredPaths = [];

        foreach ($spec['paths'] as $path => $pathInfo) {
            $lcPath = strtolower($path);
            // Match: path contains /{resource}, /{resource}/, or ends with /{resource}
            // Also match exact resource name segments like /_table/city or /_table/city/{id}
            if (str_contains($lcPath, '/' . $lcResource) ||
                str_contains($lcPath, '/{' . $lcResource . '}') ||
                $lcPath === '/' . $lcResource) {
                $filteredPaths[$path] = $pathInfo;
            }
        }

        // If exact matches found, use them. Otherwise try substring match.
        if (empty($filteredPaths)) {
            foreach ($spec['paths'] as $path => $pathInfo) {
                if (stripos($path, $resource) !== false) {
                    $filteredPaths[$path] = $pathInfo;
                }
            }
        }

        $spec['paths'] = $filteredPaths;

        // Prune schemas to only those referenced by remaining paths
        if (isset($spec['components']['schemas']) && !empty($filteredPaths)) {
            $refs = $this->collectRefs($filteredPaths);
            // Also collect refs from requestBodies and responses referenced by paths
            if (isset($spec['components']['requestBodies'])) {
                foreach ($spec['components']['requestBodies'] as $name => $body) {
                    if (in_array('#/components/requestBodies/' . $name, $refs)) {
                        $refs = array_merge($refs, $this->collectRefs($body));
                    }
                }
            }
            if (isset($spec['components']['responses'])) {
                foreach ($spec['components']['responses'] as $name => $resp) {
                    if (in_array('#/components/responses/' . $name, $refs)) {
                        $refs = array_merge($refs, $this->collectRefs($resp));
                    }
                }
            }

            // Extract schema names from refs
            $keepSchemas = [];
            foreach ($refs as $ref) {
                if (preg_match('#/components/schemas/(\w+)#', $ref, $m)) {
                    $keepSchemas[$m[1]] = true;
                    // Also follow refs inside those schemas (one level deep)
                    if (isset($spec['components']['schemas'][$m[1]])) {
                        $nestedRefs = $this->collectRefs($spec['components']['schemas'][$m[1]]);
                        foreach ($nestedRefs as $nr) {
                            if (preg_match('#/components/schemas/(\w+)#', $nr, $m2)) {
                                $keepSchemas[$m2[1]] = true;
                            }
                        }
                    }
                }
            }

            if (!empty($keepSchemas)) {
                $spec['components']['schemas'] = array_intersect_key(
                    $spec['components']['schemas'],
                    $keepSchemas
                );
            }
        }
    }

    /**
     * Recursively collect all $ref values from an array structure.
     */
    private function collectRefs($data): array
    {
        $refs = [];
        if (!is_array($data)) {
            return $refs;
        }
        foreach ($data as $key => $value) {
            if ($key === '$ref' && is_string($value)) {
                $refs[] = $value;
            } elseif (is_array($value)) {
                $refs = array_merge($refs, $this->collectRefs($value));
            }
        }
        return $refs;
    }

    /**
     * Feature: ?model=true — Build a condensed data model for LLM consumption.
     * Returns table names, columns (name + type + FK refs), row counts, and
     * relationship patterns — all in ~10-20KB instead of the full 282KB spec.
     */
    protected function handleModelResponse(ServiceRequestInterface $request, bool $refresh, bool $stockMode = false)
    {
        $model = $this->buildDataModel($refresh, $stockMode);

        $jsonStr = json_encode($model, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return new StreamedResponse(function () use ($jsonStr) {
            echo $jsonStr;
        }, 200, [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Build a condensed data model: tables → columns → types → FKs + patterns.
     */
    private function buildDataModel(bool $refresh, bool $stockMode = false): array
    {
        $model = [
            'service' => $this->name,
            'description' => $stockMode
                ? 'Condensed data model. Shows all tables, their columns with types, and foreign key references.'
                : 'Condensed data model for LLM consumption. Shows all tables, their columns with types, foreign key references, structural patterns, sample data, and enum values.',
            'tables' => [],
            'relationships' => [],
            'patterns' => [],
        ];

        if (!method_exists($this, 'getTableNames') || !method_exists($this, 'getTableSchema')) {
            $model['error'] = 'This service does not support schema introspection.';
            return $model;
        }

        try {
            $tables = $this->getTableNames();
            $allRelationships = [];

            foreach ($tables as $table) {
                $tableName = is_object($table) ? $table->name : (string)$table;
                try {
                    $schema = $this->getTableSchema($tableName);
                    if (!$schema) {
                        continue;
                    }

                    $columns = [];
                    $stringColumns = [];
                    $fields = $schema->getColumns(true);
                    foreach ($fields as $field) {
                        $col = $field->toArray();
                        $colDef = [
                            'name' => $col['name'] ?? '',
                            'type' => $col['type'] ?? 'string',
                        ];
                        if (!empty($col['is_primary_key'])) {
                            $colDef['pk'] = true;
                        }
                        if (isset($col['allow_null']) && !$col['allow_null']) {
                            $colDef['required'] = true;
                        }
                        if (!empty($col['ref_table'])) {
                            $colDef['fk'] = $col['ref_table'] . '.' . ($col['ref_field'] ?? $col['ref_table'] . '_id');
                        }
                        $columns[] = $colDef;

                        // Track string/varchar columns for enum detection
                        $colType = strtolower($col['type'] ?? '');
                        if (in_array($colType, ['string', 'text', 'varchar', 'char', 'enum'])) {
                            $stringColumns[] = $col['name'] ?? '';
                        }
                    }

                    // Get DB connection for direct queries (row count, samples, enums)
                    $dbConnection = method_exists($this, 'getConnection') ? $this->getConnection() : null;

                    // Get row count
                    $rowCount = null;
                    try {
                        if ($dbConnection) {
                            $rowCount = $dbConnection->table($tableName)->count();
                        }
                    } catch (\Exception $e) {
                        // Skip count on error
                    }

                    $tableEntry = [
                        'columns' => $columns,
                    ];
                    if ($rowCount !== null) {
                        $tableEntry['row_count'] = $rowCount;
                    }

                    if (!$stockMode) {
                        // --- Enhancement #1: Sample rows ---
                        try {
                            if ($dbConnection) {
                                $sampleRows = $dbConnection->table($tableName)->limit(3)->get();
                                if ($sampleRows->isNotEmpty()) {
                                    $tableEntry['sample_data'] = $sampleRows->map(fn($r) => (array)$r)->values()->toArray();
                                }
                            }
                        } catch (\Exception $e) {
                            // Skip sample data on error
                        }

                        // --- Enhancement #2: Distinct values for enum-like columns ---
                        if (!empty($stringColumns) && $dbConnection && $rowCount !== null && $rowCount > 0) {
                            $enumValues = $this->detectEnumValues($tableName, $stringColumns, $dbConnection);
                            if (!empty($enumValues)) {
                                $tableEntry['enum_values'] = $enumValues;
                            }
                        }
                    }

                    $model['tables'][$tableName] = $tableEntry;

                    // Collect relationships
                    $rels = $schema->getRelations(true);
                    if (!empty($rels)) {
                        $tableRels = [];
                        foreach ($rels as $rel) {
                            $relArray = $rel->toArray();
                            $relEntry = [
                                'name' => $relArray['name'] ?? '',
                                'type' => $relArray['type'] ?? '',
                                'field' => $relArray['field'] ?? '',
                                'ref_table' => $relArray['ref_table'] ?? '',
                                'ref_field' => $relArray['ref_field'] ?? '',
                                'junction_table' => $relArray['junction_table'] ?? null,
                            ];
                            if (!$stockMode) {
                                // --- Enhancement #6: Related record usage hint ---
                                $relName = $relArray['name'] ?? '';
                                if (!empty($relName)) {
                                    $relEntry['usage'] = "related={$relName}";
                                }
                            }
                            $tableRels[] = $relEntry;
                        }
                        if (!empty($tableRels)) {
                            $allRelationships[$tableName] = $tableRels;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            $model['relationships'] = $allRelationships;
            $model['patterns'] = $this->detectRelationshipPatterns($allRelationships);

            if (!$stockMode) {
                // --- Enhancement #3: Field-level semantic hints ---
                $this->injectFieldSemanticHints($model);

                // --- Enhancement #5: Auto-generated query templates ---
                $templates = $this->generateQueryTemplates($model);
                if (!empty($templates)) {
                    $model['query_templates'] = $templates;
                }
            }

        } catch (\Exception $e) {
            $model['error'] = 'Could not build data model: ' . $e->getMessage();
        }

        return $model;
    }

    /**
     * Enhancement #2: Detect enum-like columns (low cardinality string columns).
     * For columns with <= 20 distinct values, return the distinct value list.
     */
    private function detectEnumValues(string $tableName, array $stringColumns, $dbConnection): array
    {
        $enumValues = [];

        foreach ($stringColumns as $colName) {
            try {
                // Count distinct values first
                $distinctCount = $dbConnection->table($tableName)
                    ->distinct()
                    ->whereNotNull($colName)
                    ->where($colName, '!=', '')
                    ->count($colName);

                // Only include if low cardinality (≤ 20 distinct values)
                if ($distinctCount > 0 && $distinctCount <= 20) {
                    $values = $dbConnection->table($tableName)
                        ->distinct()
                        ->whereNotNull($colName)
                        ->where($colName, '!=', '')
                        ->orderBy($colName)
                        ->pluck($colName)
                        ->toArray();

                    if (!empty($values)) {
                        $enumValues[$colName] = $values;
                    }
                }
            } catch (\Exception $e) {
                // Skip column on error
                continue;
            }
        }

        return $enumValues;
    }

    /**
     * Enhancement #3: Auto-detect common field patterns and inject semantic hints.
     * Detects: amount/paid_amount pairs, soft delete flags, audit timestamps, status fields.
     */
    private function injectFieldSemanticHints(array &$model): void
    {
        foreach ($model['tables'] as $tableName => &$tableEntry) {
            $colNames = array_map(fn($c) => $c['name'] ?? '', $tableEntry['columns'] ?? []);
            $colNameSet = array_flip($colNames);
            $hints = [];

            // Detect amount + paid_amount → outstanding balance pattern
            foreach ($colNames as $name) {
                if (preg_match('/^(.*?)amount$/i', $name, $m)) {
                    $prefix = $m[1];
                    $paidCol = $prefix . 'paid_amount';
                    $pdCol = $prefix . 'pd_amount';
                    if (isset($colNameSet[$paidCol])) {
                        $hints[] = "outstanding = {$name} - {$paidCol}";
                    } elseif (isset($colNameSet[$pdCol])) {
                        $hints[] = "outstanding = {$name} - {$pdCol}";
                    }
                }
            }

            // Detect soft delete pattern
            $softDeleteCols = ['is_deleted', 'is_active', 'deleted_at', 'deleted'];
            foreach ($softDeleteCols as $sdc) {
                if (isset($colNameSet[$sdc])) {
                    if ($sdc === 'is_active') {
                        $hints[] = "Soft delete: filter by {$sdc}=true for active records";
                    } elseif ($sdc === 'deleted_at') {
                        $hints[] = "Soft delete: filter by {$sdc} IS NULL for non-deleted records";
                    } else {
                        $hints[] = "Soft delete: filter by {$sdc}=false for non-deleted records";
                    }
                    break; // one hint is enough
                }
            }

            // Detect audit timestamps
            $hasCreated = isset($colNameSet['created_at']) || isset($colNameSet['created_date']) || isset($colNameSet['create_date']);
            $hasUpdated = isset($colNameSet['updated_at']) || isset($colNameSet['updated_date']) || isset($colNameSet['last_modified_date']);
            if ($hasCreated && $hasUpdated) {
                $hints[] = 'Has audit timestamps (created/updated)';
            }

            if (!empty($hints)) {
                $tableEntry['hints'] = $hints;
            }
        }
    }

    /**
     * Enhancement #5: Auto-generate query templates from schema structure.
     * Teaches the agent optimal query patterns for this specific database.
     */
    private function generateQueryTemplates(array $model): array
    {
        $templates = [];
        $tables = $model['tables'] ?? [];
        $relationships = $model['relationships'] ?? [];

        foreach ($tables as $tableName => $tableEntry) {
            $columns = $tableEntry['columns'] ?? [];
            $rowCount = $tableEntry['row_count'] ?? 0;
            $colNames = array_map(fn($c) => $c['name'] ?? '', $columns);
            $colNameSet = array_flip($colNames);
            $colTypes = [];
            $fkColumns = [];
            $pkCol = null;
            foreach ($columns as $col) {
                $name = $col['name'] ?? '';
                $colTypes[$name] = $col['type'] ?? 'string';
                if (!empty($col['pk'])) {
                    $pkCol = $name;
                }
                if (!empty($col['fk'])) {
                    $fkColumns[$name] = $col['fk'];
                }
            }

            // Template: Count records in large tables (> 1000 rows)
            if ($rowCount > 1000) {
                $templates["count_{$tableName}"] = [
                    'description' => "Get total count of {$tableName} without fetching data ({$rowCount} rows)",
                    'tool' => 'get_table_data',
                    'params' => [
                        'tableName' => $tableName,
                        'countOnly' => true,
                    ],
                ];
            }

            // Template: Count by group for tables with FK or enum columns
            $enumCols = array_keys($tableEntry['enum_values'] ?? []);
            $groupableCols = array_merge($enumCols, array_keys($fkColumns));
            if (!empty($groupableCols) && $rowCount > 0) {
                $groupCol = $groupableCols[0]; // Pick the first groupable column
                $templates["count_{$tableName}_by_{$groupCol}"] = [
                    'description' => "Get {$tableName} count grouped by {$groupCol}",
                    'tool' => 'get_table_data',
                    'params' => [
                        'tableName' => $tableName,
                        'fields' => [$groupCol],
                        'group' => $groupCol,
                        'includeCount' => true,
                    ],
                ];
            }

            // Template: Top N by numeric column (for tables with sales/amount/total columns)
            $numericRankCols = [];
            foreach ($columns as $col) {
                $name = $col['name'] ?? '';
                $type = strtolower($col['type'] ?? '');
                if (in_array($type, ['integer', 'decimal', 'float', 'double', 'numeric', 'money'])
                    && !(!empty($col['pk'])) && empty($col['fk'])
                    && preg_match('/(sales|amount|total|revenue|quantity|count|score|rating|balance|price)/i', $name)) {
                    $numericRankCols[] = $name;
                }
            }
            foreach ($numericRankCols as $rankCol) {
                $templates["top_{$tableName}_by_{$rankCol}"] = [
                    'description' => "Get top records from {$tableName} ordered by {$rankCol} descending",
                    'tool' => 'get_table_data',
                    'params' => [
                        'tableName' => $tableName,
                        'order' => "{$rankCol} DESC",
                        'limit' => 10,
                    ],
                ];
            }

            // Template: Paginate large tables
            if ($rowCount > 1000) {
                $templates["paginate_{$tableName}"] = [
                    'description' => "Paginate through {$tableName} ({$rowCount} rows, max 1000 per page)",
                    'tool' => 'get_table_data',
                    'params' => [
                        'tableName' => $tableName,
                        'limit' => 1000,
                        'offset' => 0,
                        'includeCount' => true,
                    ],
                    'note' => 'Increment offset by limit for each page. Use filter to reduce dataset when possible.',
                ];
            }

            // Template: Join with related tables via FK
            $tableRels = $relationships[$tableName] ?? [];
            $belongsToRels = array_filter($tableRels, fn($r) => ($r['type'] ?? '') === 'belongs_to');
            if (!empty($belongsToRels)) {
                $relNames = array_map(fn($r) => $r['name'] ?? '', $belongsToRels);
                $relNames = array_filter($relNames);
                if (!empty($relNames)) {
                    $relParam = implode(',', array_slice($relNames, 0, 3)); // max 3
                    $templates["join_{$tableName}"] = [
                        'description' => "Fetch {$tableName} with related parent records included in one call",
                        'tool' => 'get_table_data',
                        'params' => [
                            'tableName' => $tableName,
                            'related' => $relParam,
                            'limit' => 10,
                        ],
                    ];
                }
            }

            // Template: Filter by date range for tables with date columns
            $dateCols = [];
            foreach ($columns as $col) {
                $type = strtolower($col['type'] ?? '');
                if (in_array($type, ['date', 'datetime', 'timestamp', 'timestamptz'])) {
                    $dateCols[] = $col['name'] ?? '';
                }
            }
            if (!empty($dateCols) && $rowCount > 100) {
                $dateCol = $dateCols[0];
                $templates["filter_{$tableName}_by_date"] = [
                    'description' => "Filter {$tableName} by date range on {$dateCol}",
                    'tool' => 'get_table_data',
                    'params' => [
                        'tableName' => $tableName,
                        'filter' => "{$dateCol} BETWEEN 2004-01-01 AND 2004-12-31",
                    ],
                    'note' => 'Adjust date range as needed. Combine with other filters using AND.',
                ];
            }
        }

        // Template: Hierarchy traversal (from detected patterns)
        $patterns = $model['patterns'] ?? [];
        foreach ($patterns as $pattern) {
            if (($pattern['type'] ?? '') === 'hierarchy') {
                $tbl = $pattern['table'] ?? '';
                $templates["traverse_hierarchy_{$tbl}"] = [
                    'description' => "Traverse {$tbl} hierarchy: fetch ALL records, build tree client-side, then aggregate recursively",
                    'steps' => [
                        "1. Fetch all {$tbl} records (use pagination if > 1000)",
                        '2. Build parent-child map from self-referencing FK',
                        '3. Recursively aggregate from leaves to root',
                        '4. Do NOT query level-by-level — fetch all at once',
                    ],
                ];
            }
        }

        return $templates;
    }

    /**
     * Build LLM-oriented usage hints as an OpenAPI extension.
     */
    private function buildUsageHints(): array
    {
        $hints = [
            'discovery' => [
                'description' => 'List all services your credentials can access',
                'endpoint' => 'GET /api/v2/',
                'note' => 'Returns only services allowed by your role/API key',
            ],
            'spec' => [
                'description' => 'Get OpenAPI 3.0 spec for any service',
                'endpoint' => 'GET /api/v2/{service_name}/_spec',
                'options' => [
                    'compact' => '?compact=true — Token-efficient summary, no schemas',
                    'resource_name' => '?resource_name={name} — Filter to one resource (e.g. a table name)',
                    'tables' => '?tables=true — Include actual table/resource names (database services)',
                    'format' => '?format=yaml — YAML output instead of JSON',
                    'refresh' => '?refresh=true — Bypass cached spec',
                ],
                'extensions' => [
                    'x-dreamfactory-resources' => 'Lists actual table names for database services',
                    'x-dreamfactory-relationships' => 'Foreign key relationships between tables with join hints',
                    'x-dreamfactory-example-request' => 'Concrete request body examples per operation',
                    'x-dreamfactory-example-response' => 'Concrete response body examples per operation',
                    'x-rate-limit' => 'Active rate limits and response headers',
                ],
            ],
            'authentication' => [
                'api_key' => 'Header: X-DreamFactory-API-Key: {key}',
                'session_token' => 'Header: X-DreamFactory-Session-Token: {jwt}',
                'get_token' => 'POST /api/v2/user/session with {"email":"...","password":"..."}',
                'admin_token' => 'POST /api/v2/system/admin/session with {"email":"...","password":"..."}',
            ],
            'querying' => [
                'filter' => [
                    'description' => 'SQL-like filtering on record endpoints',
                    'parameter' => 'filter',
                    'examples' => [
                        "filter=name='Tokyo'",
                        'filter=population>1000000',
                        "filter=(name='Tokyo') AND (countrycode='JPN')",
                        "filter=name LIKE '%york%'",
                        'filter=id IN (1,2,3)',
                    ],
                ],
                'pagination' => [
                    'description' => 'Control result set size and offset',
                    'parameters' => [
                        'limit' => 'Max records to return (e.g. limit=10)',
                        'offset' => 'Records to skip (e.g. offset=20)',
                    ],
                    'example' => '?limit=10&offset=0',
                    'note' => 'Include include_count=true to get total record count in response',
                ],
                'fields' => [
                    'description' => 'Select specific fields to return',
                    'parameter' => 'fields',
                    'example' => '?fields=id,name,population',
                ],
                'ordering' => [
                    'description' => 'Sort results',
                    'parameter' => 'order',
                    'examples' => [
                        '?order=name ASC',
                        '?order=population DESC',
                        '?order=countrycode ASC,name ASC',
                    ],
                ],
                'related' => [
                    'description' => 'Include related records via foreign keys',
                    'parameter' => 'related',
                    'example' => '?related=country_by_countrycode',
                    'note' => 'Check the schema for _by_ relationship names',
                ],
            ],
            'record_operations' => [
                'list' => 'GET /api/v2/{service}/_table/{table}',
                'get_by_id' => 'GET /api/v2/{service}/_table/{table}/{id}',
                'create' => 'POST /api/v2/{service}/_table/{table} with {"resource":[{record}]}',
                'update' => 'PUT /api/v2/{service}/_table/{table}/{id} with {record}',
                'patch' => 'PATCH /api/v2/{service}/_table/{table}/{id} with {partial_record}',
                'delete' => 'DELETE /api/v2/{service}/_table/{table}/{id}',
                'batch_create' => 'POST /api/v2/{service}/_table/{table} with {"resource":[{r1},{r2},...]}',
                'note' => 'Wrap records in {"resource":[...]} for batch operations',
            ],
        ];

        return $hints;
    }

    /**
     * Produce a compact, token-efficient version of the spec.
     * Strips schemas, full descriptions, and response details.
     * Keeps: paths, methods, summaries, operationIds, parameter names/types.
     */
    private function compactSpec(array $spec): array
    {
        $compact = [
            'openapi' => $spec['openapi'],
            'info' => $spec['info'],
            'servers' => $spec['servers'],
            'security' => $spec['security'],
            'x-dreamfactory-usage' => $spec['x-dreamfactory-usage'] ?? [],
            'endpoints' => [],
        ];

        // Preserve relationship and resource metadata in compact mode — these are
        // critical for LLMs to understand table connections and hierarchies
        if (isset($spec['x-dreamfactory-relationships'])) {
            $compact['x-dreamfactory-relationships'] = $spec['x-dreamfactory-relationships'];
        }
        if (isset($spec['x-dreamfactory-resources'])) {
            $compact['x-dreamfactory-resources'] = $spec['x-dreamfactory-resources'];
        }

        foreach ($spec['paths'] as $path => $methods) {
            foreach ($methods as $method => $operation) {
                $endpoint = [
                    'path' => $path,
                    'method' => strtoupper($method),
                ];
                if (isset($operation['summary'])) {
                    $endpoint['summary'] = $operation['summary'];
                }
                if (isset($operation['operationId'])) {
                    $endpoint['operationId'] = $operation['operationId'];
                }

                // Compact parameters: just name, location, required, type
                if (!empty($operation['parameters'])) {
                    $params = [];
                    foreach ($operation['parameters'] as $param) {
                        $p = $param['name'] . ' (' . ($param['in'] ?? 'query');
                        if (!empty($param['required'])) {
                            $p .= ', required';
                        }
                        $schema = $param['schema'] ?? [];
                        if (!empty($schema['type'])) {
                            $p .= ', ' . $schema['type'];
                        }
                        if (!empty($schema['enum'])) {
                            $p .= ': ' . implode('|', $schema['enum']);
                        }
                        $p .= ')';
                        $params[] = $p;
                    }
                    $endpoint['parameters'] = $params;
                }

                // Note if it has a request body
                if (isset($operation['requestBody'])) {
                    $endpoint['has_request_body'] = true;
                }

                $compact['endpoints'][] = $endpoint;
            }
        }

        $compact['endpoint_count'] = count($compact['endpoints']);

        return $compact;
    }

    /**
     * Format the spec as JSON or YAML StreamedResponse.
     */
    private function formatSpecResponse(array $spec, ServiceRequestInterface $request): StreamedResponse
    {
        $format = strtolower($request->getParameter('format', ''));
        if ($format === 'yml') {
            $format = 'yaml';
        }
        if (!in_array($format, ['json', 'yaml'])) {
            $accept = request()->header('Accept', '');
            $format = (str_contains($accept, 'yaml') || str_contains($accept, 'yml')) ? 'yaml' : 'json';
        }

        if ($format === 'yaml') {
            $yamlStr = Yaml::dump($spec, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            return new StreamedResponse(function () use ($yamlStr) {
                echo $yamlStr;
            }, 200, [
                'Content-Type'  => 'application/x-yaml; charset=utf-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        $jsonStr = json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return new StreamedResponse(function () use ($jsonStr) {
            echo $jsonStr;
        }, 200, [
            'Content-Type'  => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Feature #1: Inject request/response examples into operations.
     * Generates concrete examples from schemas so LLMs see actual data shapes.
     */
    private function injectExamples(array &$spec): void
    {
        foreach ($spec['paths'] as $path => &$methods) {
            foreach ($methods as $method => &$operation) {
                if (!is_array($operation)) {
                    continue;
                }

                $opId = $operation['operationId'] ?? '';
                $lcMethod = strtolower($method);

                // Generate request body examples for POST/PUT/PATCH
                if (in_array($lcMethod, ['post', 'put', 'patch']) && isset($operation['requestBody'])) {
                    $reqExample = $this->generateRequestExample($operation, $spec, $lcMethod, $path);
                    if (!empty($reqExample)) {
                        $operation['x-dreamfactory-example-request'] = $reqExample;
                    }
                }

                // Generate response examples for GET operations
                if ($lcMethod === 'get') {
                    $respExample = $this->generateResponseExample($operation, $spec, $path);
                    if (!empty($respExample)) {
                        $operation['x-dreamfactory-example-response'] = $respExample;
                    }
                }
            }
        }
    }

    /**
     * Generate a request body example from schema.
     */
    private function generateRequestExample(array $operation, array $spec, string $method, string $path): array
    {
        // For table endpoints (CRUD)
        if (str_contains($path, '/_table/')) {
            if (str_contains($path, '{id}') || str_contains($path, '{table_name}/{id}')) {
                // Single record update
                return [
                    'description' => 'Update a single record by ID',
                    'body' => ['field_name' => 'new_value', 'another_field' => 'another_value'],
                ];
            }
            if ($method === 'post') {
                return [
                    'description' => 'Create one or more records',
                    'body' => ['resource' => [['field1' => 'value1', 'field2' => 'value2']]],
                    'note' => 'Wrap records in {"resource":[...]} array for batch operations',
                ];
            }
            if ($method === 'patch') {
                return [
                    'description' => 'Update records matching filter or by IDs',
                    'body' => ['resource' => [['id' => 1, 'field_name' => 'new_value']]],
                    'note' => 'Include primary key in each record for ID-based updates, or use ?filter= for filtered updates',
                ];
            }
        }

        // For schema endpoints
        if (str_contains($path, '/_schema')) {
            return [
                'description' => 'Create or modify database schema',
                'body' => ['resource' => [['name' => 'new_table', 'field' => [['name' => 'id', 'type' => 'id'], ['name' => 'name', 'type' => 'string', 'length' => 255]]]]],
            ];
        }

        return [];
    }

    /**
     * Generate a response example from schema.
     */
    private function generateResponseExample(array $operation, array $spec, string $path): array
    {
        if (str_contains($path, '/_table/') && !str_contains($path, '{id}')) {
            // Table list endpoint
            return [
                'description' => 'Returns an array of records wrapped in resource key',
                'body' => ['resource' => [['id' => 1, 'field1' => 'value1'], ['id' => 2, 'field1' => 'value2']]],
                'note' => 'Add ?include_count=true to get total record count alongside results',
            ];
        }

        if (str_contains($path, '/_table/') && (str_contains($path, '{id}') || str_contains($path, '/{table_name}/{id}'))) {
            return [
                'description' => 'Returns a single record object',
                'body' => ['id' => 1, 'field1' => 'value1', 'field2' => 'value2'],
            ];
        }

        if ($path === '/_table' || $path === '/_schema') {
            return [
                'description' => 'Returns a list of available resources',
                'body' => ['resource' => [['name' => 'table_name']]],
            ];
        }

        return [];
    }

    /**
     * Feature #2: Inject resource list (table names) for database services.
     * When ?tables=true or in full mode, adds x-dreamfactory-resources with actual table names.
     */
    private function injectResourceList(array &$spec, bool $explicit): void
    {
        // Only for database services that have getTableNames()
        if (!method_exists($this, 'getTableNames')) {
            return;
        }

        try {
            $tables = $this->getTableNames();
            $tableNames = [];
            foreach ($tables as $table) {
                $tableNames[] = is_object($table) ? $table->name : (string)$table;
            }
            sort($tableNames);

            $spec['x-dreamfactory-resources'] = [
                'type' => 'database',
                'tables' => $tableNames,
                'count' => count($tableNames),
                'note' => 'Use these names with /_table/{table_name} endpoints',
            ];
        } catch (\Exception $e) {
            // Silently skip if table listing fails
            Log::debug('[_spec] Could not list tables: ' . $e->getMessage());
        }
    }

    /**
     * Feature #3: Inject standard DreamFactory error response schemas.
     */
    private function injectErrorSchemas(array &$spec): void
    {
        // Add error schema to components
        $spec['components']['schemas']['DreamFactoryError'] = [
            'type' => 'object',
            'properties' => [
                'error' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => [
                            'type' => 'integer',
                            'description' => 'HTTP status code',
                            'example' => 400,
                        ],
                        'context' => [
                            'type' => 'object',
                            'description' => 'Additional error context (varies by error type)',
                            'nullable' => true,
                        ],
                        'message' => [
                            'type' => 'string',
                            'description' => 'Human-readable error message',
                            'example' => 'Bad request.',
                        ],
                        'status_code' => [
                            'type' => 'integer',
                            'description' => 'HTTP status code (duplicated for compatibility)',
                            'example' => 400,
                        ],
                    ],
                    'required' => ['code', 'message'],
                ],
            ],
        ];

        // Add standard error responses to components
        $errorResponses = [
            '400' => ['description' => 'Bad Request — Missing or invalid parameters', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DreamFactoryError']]]],
            '401' => ['description' => 'Unauthorized — Missing or invalid authentication token/key', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DreamFactoryError']]]],
            '403' => ['description' => 'Forbidden — Authenticated but insufficient permissions for this service/resource', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DreamFactoryError']]]],
            '404' => ['description' => 'Not Found — Resource or record does not exist', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DreamFactoryError']]]],
            '429' => ['description' => 'Too Many Requests — Rate limit exceeded. Check Retry-After header.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DreamFactoryError']]]],
            '500' => ['description' => 'Internal Server Error — Server-side failure', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DreamFactoryError']]]],
        ];

        // Inject error responses into each operation
        foreach ($spec['paths'] as $path => &$methods) {
            foreach ($methods as $method => &$operation) {
                if (!is_array($operation) || !isset($operation['operationId'])) {
                    continue;
                }
                if (!isset($operation['responses'])) {
                    $operation['responses'] = [];
                }
                // Add error responses that aren't already defined
                foreach ($errorResponses as $code => $response) {
                    if (!isset($operation['responses'][$code])) {
                        $operation['responses'][$code] = $response;
                    }
                }
            }
        }
    }

    /**
     * Feature #4: Inject natural language operation descriptions.
     * Maps operationId patterns to human-readable descriptions.
     */
    private function injectNaturalLanguageDescriptions(array &$spec): void
    {
        $descriptionMap = [
            // Database table operations
            'getRecordsByTable' => 'Retrieve records from a database table. Supports filtering, pagination, field selection, ordering, and related record joins.',
            'createRecords' => 'Create one or more new records in a database table. Wrap records in {"resource":[...]} array.',
            'replaceRecordsByTable' => 'Replace (full update) one or more records in a database table by ID.',
            'updateRecordsByTable' => 'Partially update one or more records in a database table. Only specified fields are changed.',
            'deleteRecordsByTable' => 'Delete one or more records from a database table by ID or filter.',
            'getRecordById' => 'Retrieve a single record from a database table by its primary key ID.',
            'replaceRecordById' => 'Replace (full update) a single record by its primary key ID.',
            'updateRecordById' => 'Partially update a single record by its primary key ID. Only specified fields are changed.',
            'deleteRecordById' => 'Delete a single record from a database table by its primary key ID.',
            // Schema operations
            'getSchemas' => 'List all available database tables and views in this service.',
            'createSchema' => 'Create a new database table with specified columns and constraints.',
            'updateSchema' => 'Modify database table structure (add/modify columns, indexes, constraints).',
            'deleteSchema' => 'Drop a database table. This permanently removes the table and all its data.',
            'getSchema' => 'Get the full schema definition for a database table, including columns, types, and constraints.',
            'describeTable' => 'Get the full schema definition for a database table, including columns, types, and relationships.',
            'describeField' => 'Get detailed information about a specific column in a database table.',
            'describeRelationships' => 'List all foreign key relationships for a database table.',
            'describeRelationship' => 'Get details of a specific foreign key relationship.',
            // Stored procedures and functions
            'getStoredProcs' => 'List all available stored procedures in this database service.',
            'callStoredProc' => 'Execute a stored procedure with optional input/output parameters.',
            'callStoredProcWithPayload' => 'Execute a stored procedure with parameters sent in the request body.',
            'getStoredFuncs' => 'List all available stored functions in this database service.',
            'callStoredFunc' => 'Execute a stored function with optional parameters.',
            'callStoredFuncWithPayload' => 'Execute a stored function with parameters sent in the request body.',
            // System service operations
            'getAdmins' => 'List all administrator accounts.',
            'createAdmins' => 'Create new administrator accounts.',
            'getAdmin' => 'Get details of a specific administrator by ID.',
            'updateAdmin' => 'Update an administrator account.',
            'deleteAdmin' => 'Delete an administrator account.',
            'getApps' => 'List all registered applications.',
            'createApps' => 'Register a new application.',
            'getApp' => 'Get details of a specific application by ID.',
            'updateApp' => 'Update an application registration.',
            'deleteApp' => 'Delete an application registration.',
            'getRoles' => 'List all roles with their permission sets.',
            'createRoles' => 'Create a new role with service-level permissions.',
            'getRole' => 'Get details of a specific role by ID.',
            'updateRole' => 'Update a role and its permissions.',
            'deleteRole' => 'Delete a role.',
            'getServices' => 'List all configured services (databases, APIs, file storage, etc.).',
            'createService' => 'Create a new service connection.',
            'getService' => 'Get details of a specific service by ID.',
            'updateService' => 'Update a service configuration.',
            'deleteService' => 'Delete a service connection.',
            'getUsers' => 'List all user accounts.',
            'createUsers' => 'Create new user accounts.',
            'getUser' => 'Get details of a specific user by ID.',
            'updateUser' => 'Update a user account.',
            'deleteUser' => 'Delete a user account.',
            // Session / auth
            'adminLogin' => 'Authenticate as an administrator and receive a JWT session token.',
            'adminLogout' => 'Invalidate the current admin session token.',
            'getAdminProfile' => 'Get the current administrator profile.',
            'updateAdminProfile' => 'Update the current administrator profile.',
            'changeAdminPassword' => 'Change the current administrator password.',
            'userLogin' => 'Authenticate as a user and receive a JWT session token.',
            'userLogout' => 'Invalidate the current user session token.',
            'getUserProfile' => 'Get the current user profile.',
            'updateUserProfile' => 'Update the current user profile.',
            'changeUserPassword' => 'Change the current user password.',
            'userRegister' => 'Register a new user account (if open registration is enabled).',
            // File operations
            'getResources' => 'List all available resources in this service.',
            'getResourceList' => 'List files and folders in the root directory.',
            'getFolder' => 'List contents of a folder.',
            'createFolder' => 'Create a new folder.',
            'deleteFolder' => 'Delete a folder and optionally its contents.',
            'getFile' => 'Download or read a file.',
            'createFile' => 'Upload a new file.',
            'replaceFile' => 'Replace an existing file.',
            'deleteFile' => 'Delete a file.',
        ];

        foreach ($spec['paths'] as $path => &$methods) {
            foreach ($methods as $method => &$operation) {
                if (!is_array($operation)) {
                    continue;
                }
                $opId = $operation['operationId'] ?? '';

                // Direct match
                if (isset($descriptionMap[$opId])) {
                    $operation['description'] = $descriptionMap[$opId];
                    continue;
                }

                // Pattern-based matching for common DreamFactory patterns
                if (preg_match('/^get(\w+)s$/', $opId) && !isset($operation['description'])) {
                    $resource = preg_replace('/^get/', '', $opId);
                    $resource = rtrim($resource, 's');
                    $operation['description'] = "List all {$resource} records.";
                }
                if (preg_match('/^get(\w+)$/', $opId) && str_contains($path, '{id}') && !isset($operation['description'])) {
                    $resource = preg_replace('/^get/', '', $opId);
                    $operation['description'] = "Get a specific {$resource} by ID.";
                }
                if (preg_match('/^create(\w+)$/', $opId) && !isset($operation['description'])) {
                    $resource = preg_replace('/^create/', '', $opId);
                    $operation['description'] = "Create new {$resource} records.";
                }
                if (preg_match('/^update(\w+)$/', $opId) && !isset($operation['description'])) {
                    $resource = preg_replace('/^update/', '', $opId);
                    $operation['description'] = "Update {$resource} records.";
                }
                if (preg_match('/^delete(\w+)$/', $opId) && !isset($operation['description'])) {
                    $resource = preg_replace('/^delete/', '', $opId);
                    $operation['description'] = "Delete {$resource} records.";
                }
            }
        }
    }

    /**
     * Feature #6: Inject relationship metadata for database services.
     * Adds x-dreamfactory-relationships with FK info so LLMs know how tables connect.
     */
    private function injectRelationships(array &$spec): void
    {
        if (!method_exists($this, 'getTableNames') || !method_exists($this, 'getTableSchema')) {
            return;
        }

        try {
            $tables = $this->getTableNames();
            $relationships = [];

            foreach ($tables as $table) {
                $tableName = is_object($table) ? $table->name : (string)$table;
                try {
                    $schema = $this->getTableSchema($tableName);
                    if (!$schema) {
                        continue;
                    }
                    $rels = $schema->getRelations(true);
                    if (empty($rels)) {
                        continue;
                    }

                    $tableRels = [];
                    foreach ($rels as $rel) {
                        $relArray = $rel->toArray();
                        $tableRels[] = [
                            'name' => $relArray['name'] ?? '',
                            'type' => $relArray['type'] ?? '',
                            'field' => $relArray['field'] ?? '',
                            'ref_table' => $relArray['ref_table'] ?? '',
                            'ref_field' => $relArray['ref_field'] ?? '',
                            'junction_table' => $relArray['junction_table'] ?? null,
                            'description' => $this->describeRelationship($relArray),
                        ];
                    }
                    if (!empty($tableRels)) {
                        $relationships[$tableName] = $tableRels;
                    }
                } catch (\Exception $e) {
                    // Skip tables that fail
                    continue;
                }
            }

            if (!empty($relationships)) {
                // Detect structural patterns that LLMs need to handle specially
                $patterns = $this->detectRelationshipPatterns($relationships);

                $spec['x-dreamfactory-relationships'] = [
                    'description' => 'Foreign key relationships between tables. Use the relationship name with ?related={name} to join records.',
                    'tables' => $relationships,
                ];

                if (!empty($patterns)) {
                    $spec['x-dreamfactory-relationships']['patterns'] = $patterns;
                }
            }
        } catch (\Exception $e) {
            Log::debug('[_spec] Could not load relationships: ' . $e->getMessage());
        }
    }

    /**
     * Generate a human-readable description of a relationship.
     */
    private function describeRelationship(array $rel): string
    {
        $type = $rel['type'] ?? '';
        $field = $rel['field'] ?? '';
        $refTable = $rel['ref_table'] ?? '';
        $refField = $rel['ref_field'] ?? '';
        $junction = $rel['junction_table'] ?? '';

        switch ($type) {
            case 'belongs_to':
                return "This table's {$field} references {$refTable}.{$refField}";
            case 'has_one':
                return "{$refTable}.{$refField} references this table's {$field} (one-to-one)";
            case 'has_many':
                return "{$refTable}.{$refField} references this table's {$field} (one-to-many)";
            case 'many_many':
                return "Many-to-many with {$refTable} via junction table {$junction}";
            default:
                return "{$type}: {$field} → {$refTable}.{$refField}";
        }
    }

    /**
     * Detect structural patterns in relationships that LLMs need to handle specially.
     * Identifies self-referencing hierarchies and many-to-many junction tables.
     */
    private function detectRelationshipPatterns(array $relationships): array
    {
        $patterns = [];

        $seenHierarchies = [];
        foreach ($relationships as $tableName => $rels) {
            foreach ($rels as $rel) {
                // Self-referencing FK = hierarchy/tree structure
                // DreamFactory may report this as belongs_to (child→parent) or has_many (parent→children)
                if (($rel['ref_table'] ?? '') === $tableName) {
                    // Deduplicate: only report each table's hierarchy once
                    $hierKey = $tableName;
                    if (isset($seenHierarchies[$hierKey])) {
                        continue;
                    }
                    $seenHierarchies[$hierKey] = true;

                    // For has_many self-ref, the FK column is ref_field (the child column pointing to parent)
                    $childField = ($rel['type'] === 'has_many') ? ($rel['ref_field'] ?? '') : ($rel['field'] ?? '');
                    $parentField = ($rel['type'] === 'has_many') ? ($rel['field'] ?? '') : ($rel['ref_field'] ?? '');

                    $patterns['hierarchies'][] = [
                        'table' => $tableName,
                        'child_field' => $childField,
                        'parent_field' => $parentField,
                        'hint' => "IMPORTANT: {$tableName}.{$childField} references {$tableName}.{$parentField} — this is a parent-child HIERARCHY. "
                            . "Records form a TREE structure. You MUST use recursive traversal to find all descendants, "
                            . "not just direct children. Aggregate metrics (budget, headcount, salary) must roll up through ALL levels.",
                    ];
                }

                // Many-to-many via junction table
                if (($rel['type'] ?? '') === 'many_many' && !empty($rel['junction_table'])) {
                    $patterns['junction_tables'][] = [
                        'table' => $tableName,
                        'related_table' => $rel['ref_table'] ?? '',
                        'junction' => $rel['junction_table'],
                        'hint' => "Many-to-many: join {$tableName} to {$rel['ref_table']} via {$rel['junction_table']}.",
                    ];
                }
            }
        }

        return $patterns;
    }

    /**
     * Feature #7: Inject rate limit information into the spec.
     */
    private function injectRateLimits(array &$spec): void
    {
        try {
            // Check if the Limit model exists (df-limits package)
            if (!class_exists('\\DreamFactory\\Core\\Limit\\Models\\Limit')) {
                return;
            }

            $limitClass = '\\DreamFactory\\Core\\Limit\\Models\\Limit';
            $limits = $limitClass::where('is_active', 1)->get();

            if ($limits->isEmpty()) {
                $spec['x-rate-limit'] = [
                    'description' => 'No active rate limits configured for this instance.',
                    'note' => 'Admins can configure rate limits via GET /api/v2/system/limit',
                ];
                return;
            }

            $periods = ['minute', 'hour', 'day', '7-day', '30-day'];
            $serviceLimits = [];
            $instanceLimits = [];

            foreach ($limits as $limit) {
                $info = [
                    'name' => $limit->name ?? '',
                    'rate' => $limit->rate ?? 0,
                    'period' => $periods[$limit->period] ?? 'unknown',
                    'type' => $limit->type ?? '',
                ];

                if (!empty($limit->verb)) {
                    $info['verb'] = $limit->verb;
                }
                if (!empty($limit->endpoint)) {
                    $info['endpoint'] = $limit->endpoint;
                }

                // Categorize: service-specific vs instance-wide
                if (!empty($limit->service_id) && $limit->service_id == $this->getServiceId()) {
                    $serviceLimits[] = $info;
                } elseif (empty($limit->service_id)) {
                    $instanceLimits[] = $info;
                }
            }

            $rateLimitInfo = [
                'description' => 'Rate limits that apply to API requests',
                'headers' => [
                    'X-RateLimit-Limit' => 'Max requests allowed in the period',
                    'X-RateLimit-Remaining' => 'Requests remaining in current period',
                    'Retry-After' => 'Seconds to wait before retrying (only on 429)',
                    'X-RateLimit-Reset' => 'Unix timestamp when the limit resets',
                ],
            ];

            if (!empty($serviceLimits)) {
                $rateLimitInfo['service_limits'] = $serviceLimits;
            }
            if (!empty($instanceLimits)) {
                $rateLimitInfo['instance_limits'] = $instanceLimits;
            }
            if (empty($serviceLimits) && empty($instanceLimits)) {
                $rateLimitInfo['note'] = 'No rate limits apply directly to this service, but instance-level or role-level limits may still apply.';
            }

            $spec['x-rate-limit'] = $rateLimitInfo;
        } catch (\Exception $e) {
            // Silently skip if rate limit module not available
            Log::debug('[_spec] Could not load rate limits: ' . $e->getMessage());
        }
    }
}