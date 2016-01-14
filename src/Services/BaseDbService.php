<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\Cacheable;
use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Database\TableSchema;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Resources\BaseDbResource;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;

abstract class BaseDbService extends BaseRestService implements CachedInterface
{
    use Cacheable;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Array of resource defining arrays
     */
    protected static $resources = [];
    /**
     * @type bool
     */
    protected $cacheEnabled = false;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new SqlDbSvc
     *
     * @param array $settings
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    public function __construct($settings = [])
    {
        parent::__construct($settings);

        $config = ArrayUtils::clean(ArrayUtils::get($settings, 'config'));
        $this->cacheEnabled = ArrayUtils::getBool($config, 'cache_enabled');
        $this->cacheTTL = intval(ArrayUtils::get($config, 'cache_ttl', \Config::get('df.default_cache_ttl')));
        $this->cachePrefix = 'service_' . $this->id . ':';
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessList()
    {
        $output = parent::getAccessList();
        $refresh = $this->request->getParameterAsBool(ApiOptions::REFRESH);
        $schema = $this->request->getParameter(ApiOptions::SCHEMA, '');

        foreach (static::$resources as $resourceInfo) {
            $className = $resourceInfo['class_name'];

            if (!class_exists($className)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $this->resourcePath);
            }

            /** @var BaseDbResource $resource */
            $resource = $this->instantiateResource($className, $resourceInfo);
            $access = $this->getPermissions($resource->name);
            if (!empty($access)) {
                try {
                    $results = $resource->listAccessComponents($schema, $refresh);
                    $output[] = $resource->name . '/';
                    $output[] = $resource->name . '/*';
                    $output = array_merge($output, $results);
                } catch (NotImplementedException $ex) {
                    // carry on
                }
            }
        }

        return $output;
    }

    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        return ($only_handlers) ? static::$resources : array_values(static::$resources);
    }

    /**
     * @param string|null $schema
     * @param bool        $refresh
     * @param bool        $use_alias
     *
     * @return TableSchema[]
     */
    abstract public function getTableNames($schema = null, $refresh = false, $use_alias = false);

    /**
     */
    abstract public function refreshTableCache();

    /**
     * {@InheritDoc}
     */
    protected function handleResource(array $resources)
    {
        try {
            return parent::handleResource($resources);
        } catch (NotFoundException $ex) {
            // If version 1.x, the resource could be a table
//            if ($this->request->getApiVersion())
//            {
//                $resource = $this->instantiateResource( Table::class, [ 'name' => $this->resource ] );
//                $newPath = $this->resourceArray;
//                array_shift( $newPath );
//                $newPath = implode( '/', $newPath );
//
//                return $resource->handleRequest( $this->request, $newPath, $this->outputFormat );
//            }

            throw $ex;
        }
    }

    public static function getApiDocInfo(Service $service)
    {
        $base = parent::getApiDocInfo($service);

        $apis = [];
        $models = [];
        foreach (static::$resources as $resourceInfo) {
            $resourceClass = ArrayUtils::get($resourceInfo, 'class_name');

            if (!class_exists($resourceClass)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $resourceClass);
            }

            $resourceName = ArrayUtils::get($resourceInfo, static::RESOURCE_IDENTIFIER);
            $access = Session::getServicePermissions($service->name, $resourceName, ServiceRequestorTypes::API);
            if (!empty($access)) {
                $results = $resourceClass::getApiDocInfo($service, $resourceInfo);
                if (isset($results, $results['paths'])) {
                    $apis = array_merge($apis, $results['paths']);
                }
                if (isset($results, $results['definitions'])) {
                    $models = array_merge($models, $results['definitions']);
                }
            }
        }

        $base['paths'] = array_merge($base['paths'], $apis);
        $base['definitions'] = array_merge($base['definitions'], $models);

        return $base;
    }
}