<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\ServiceRequestInterface;
use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Enums\ApiDocFormatTypes;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Components\RestHandler;
use DreamFactory\Core\Contracts\ServiceInterface;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResourcesWrapper;
use DreamFactory\Core\Utility\Session;
use ServiceManager as ServiceMgr;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BaseRestService
 *
 * @package DreamFactory\Core\Services
 */
class BaseRestService extends RestHandler implements ServiceInterface
{
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
        $this->doc = (array)array_get($settings, 'service_doc_by_service_id');
        //  Replace any private lookups
        Session::replaceLookups($this->config, true);
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
        if (!$this->isActive) {
            throw new ForbiddenException("Service {$this->name} is deactivated.");
        }

        return parent::handleRequest($request, $resource);
    }

    /**
     * {@inheritdoc}
     */
    public function getResources($onlyHandlers = false)
    {
        return ($onlyHandlers) ? static::$resources : array_values(static::$resources);
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
            foreach ($this->getResources(true) as $resourceInfo) {
                $className = $resourceInfo['class_name'];

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

        if (!empty($name)) {
            $paths = array_get($content, 'paths', []);
            // tricky here, loop through all indexes to check if all start with service name,
            // otherwise need to prepend service name to all.
            if (!empty(array_filter(array_keys($paths), function ($k) use ($name) {
                $k = ltrim($k, '/');
                if (false !== strpos($k, '/')) {
                    $k = strstr($k, '/', true);
                }

                return (0 !== strcasecmp($name, $k));
            }))
            ) {
                $newPaths = [];
                foreach ($paths as $path => $pathDef) {
                    $newPath = '/' . $name . $path;
                    $newPaths[$newPath] = $pathDef;
                }
                $paths = $newPaths;
            }
            // make sure each path is tagged
            foreach ($paths as $path => &$pathDef) {
                foreach ($pathDef as $verb => &$verbDef) {
                    // If we leave the incoming tags, they get bubbled up to our service-level
                    // and possibly confuse the whole interface. Replace with our service name tag.
//                    if (!is_array($tag = array_get($verbDef, 'tags', []))) {
//                        $tag = [];
//                    }
//                    if (false === array_search($name, $tag)) {
//                        $tag[] = $name;
//                        $verbDef['tags'] = $tag;
//                    }
                    switch (strtolower($verb)) {
                        case 'get':
                        case 'post':
                        case 'put':
                        case 'patch':
                        case 'delete':
                        case 'options':
                        case 'head':
                            $verbDef['tags'] = [$name];
                            break;
                    }
                }
            }
            $content['paths'] = $paths; // write any changes back
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

            $eventPath = str_replace('/', '.', trim($path, '/'));
            $resourcePath = ltrim(strstr(trim($path, '/'), '/'), '/');
            $replacePos = strpos($resourcePath, '{');

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
                                $checkFirstOption = strstr(substr($resourcePath, $replacePos + 1), '}', true);
                                if ($name !== $checkFirstOption) {
                                    continue;
                                }
                                $options = [];
                                // try to match any access path
                                foreach ($access as $accessPath) {
                                    $accessPath = rtrim($accessPath, '/*');
                                    if (!empty($accessPath) && (strlen($accessPath) > $replacePos)) {
                                        if (0 === substr_compare($accessPath, $resourcePath, 0, $replacePos)) {
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

    public function getApiDoc()
    {
        if (!empty($this->doc)) {
            if (!empty($content = array_get($this->doc, 'content'))) {
                if (is_string($content)) {
                    // need to convert to array format for handling
                    $info = [
                        'name'        => $this->name,
                        'label'       => $this->label,
                        'description' => $this->description,
                    ];
                    return $this->storedContentToArray($content, array_get($this->doc, 'format'), $info);
                } elseif (is_array($content)) {
                    return $content;
                }
            } else {
                return [];
            }
            if (is_array($content)) {
                return $content;
            }
        }
        return $this->getApiDocInfo($this);
    }

    public static function getApiDocInfo($service)
    {
        $name = strtolower($service->name);
        $capitalized = camelize($service->name);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $pluralClass = str_plural($class);
        $wrapper = ResourcesWrapper::getWrapper();

        $base = [
            'paths'       => [
                '/' . $name => [
                    'get' => [
                        'tags'        => [$name],
                        'summary'     => 'get' . $capitalized . 'Resources() - Get resources for this service.',
                        'operationId' => 'get' . $capitalized . 'Resources',
                        'description' => 'Return an array of the resources available.',
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
                            '200'     => [
                                'description' => 'Success',
                                'schema'      => ['$ref' => '#/definitions/' . $pluralClass . 'Response']
                            ],
                            'default' => [
                                'description' => 'Error',
                                'schema'      => ['$ref' => '#/definitions/Error']
                            ]
                        ],
                    ],
                ],
            ],
            'definitions' => [
                $class . 'Response'       => [
                    'type'       => 'object',
                    'properties' => [
                        static::getResourceIdentifier() => [
                            'type'        => 'string',
                            'description' => 'Identifier of the resource.',
                        ],
                    ],
                ],
                $pluralClass . 'Response' => [
                    'type'       => 'object',
                    'properties' => [
                        $wrapper => [
                            'type'        => 'array',
                            'description' => 'Array of resources available to this service.',
                            'items'       => [
                                '$ref' => '#/definitions/' . $class . 'Response',
                            ],
                        ],
                    ],
                ],
            ],
            'parameters' => [],
        ];

        $apis = [];
        $models = static::getDefaultModels();
        $parameters = ApiOptions::getSwaggerGlobalParameters();
        foreach (static::$resources as $resourceInfo) {
            $resourceClass = array_get($resourceInfo, 'class_name');

            if (!class_exists($resourceClass)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $resourceClass);
            }

            $results = $resourceClass::getApiDocInfo($service->name, $resourceInfo);
            if (isset($results, $results['paths'])) {
                $apis = array_merge($apis, $results['paths']);
            }
            if (isset($results, $results['definitions'])) {
                $models = array_merge($models, $results['definitions']);
            }
        }

        $base['paths'] = array_merge($base['paths'], $apis);
        $base['definitions'] = array_merge($base['definitions'], $models);
        $base['parameters'] = array_merge($base['parameters'], $parameters);
        unset($base['paths']['/' . $service->name]['get']['parameters']);

        return $base;
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
}