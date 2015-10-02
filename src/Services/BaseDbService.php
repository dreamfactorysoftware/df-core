<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\Cacheable;
use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Resources\BaseDbResource;
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
    protected $resources = [];
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

        foreach ($this->resources as $resourceInfo) {
            $className = $resourceInfo['class_name'];

            if (!class_exists($className)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $this->resourcePath);
            }

            /** @var BaseDbResource $resource */
            $resource = $this->instantiateResource($className, $resourceInfo);
            $access = $this->getPermissions($resource->name);
            if (!empty($access)) {
                try{
                    $results = $resource->listAccessComponents($schema, $refresh);
                    $output[] = $resource->name . '/';
                    $output[] = $resource->name . '/*';
                    $output = array_merge($output, $results);

                } catch ( NotImplementedException $ex){
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
        return ($only_handlers) ? $this->resources : array_values($this->resources);
    }

    /**
     * @param string|null $schema
     * @param bool        $refresh
     * @param bool        $use_alias
     *
     * @return array
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

    /**
     * {@inheritdoc}
     */
    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $apis = [];
        $models = [];
        foreach ($this->resources as $resourceInfo) {
            $className = $resourceInfo['class_name'];

            if (!class_exists($className)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $this->resourcePath);
            }

            /** @var BaseDbResource $resource */
            $resource = $this->instantiateResource($className, $resourceInfo);

            $access = $this->getPermissions($resource->name);
            if (!empty($access)) {
                $results = $resource->getApiDocInfo();
                if (isset($results, $results['apis'])) {
                    $apis = array_merge($apis, $results['apis']);
                }
                if (isset($results, $results['models'])) {
                    $models = array_merge($models, $results['models']);
                }
            }
        }

        $base['apis'] = array_merge($base['apis'], $apis);
        $base['models'] = array_merge($base['models'], $models);

        return $base;
    }
}