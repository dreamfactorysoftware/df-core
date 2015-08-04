<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Models\SystemResource;

class System extends BaseRestService
{
    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        return SystemResource::all()->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessList()
    {
        $list = parent::getAccessList();
        $nameField = $this->getResourceIdentifier();
        foreach ($this->getResources() as $resource)
        {
            $name = ArrayUtils::get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                $list[] = $name . '/';
                $list[] = $name . '/*';
            }
        }

        return $list;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $apis = [];
        $models = [];

        foreach ($this->getResources(true) as $resourceInfo) {
            $className = ArrayUtils::get($resourceInfo, 'class_name');

            if (!class_exists($className)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $this->resourcePath);
            }

            /** @var BaseRestResource $resource */
            $resource = $this->instantiateResource($className, $resourceInfo);

            $name = ArrayUtils::get($resourceInfo, static::RESOURCE_IDENTIFIER, '') . '/';
            $access = $this->getPermissions($name);
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