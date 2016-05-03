<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Contracts\SystemResourceTypeInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\System\BaseSystemResource;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use SystemResourceManager;

class System extends BaseRestService
{
    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        $resources = [];
        $types = SystemResourceManager::getResourceTypes();
        /** @type SystemResourceTypeInterface $type */
        foreach ($types as $type) {
            $resources[] = $type->toArray();
        }

        return $resources;
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessList()
    {
        $list = parent::getAccessList();
        $nameField = static::getResourceIdentifier();
        foreach ($this->getResources() as $resource) {
            $name = ArrayUtils::get($resource, $nameField);
            if (!empty($this->getPermissions())) {
                $list[] = $name . '/';
                $list[] = $name . '/*';
            }
        }

        return $list;
    }

    public function getApiDocInfo()
    {
        $base = parent::getApiDocInfo();

        $apis = [];
        $models = [];
        $resources = SystemResourceManager::getResourceTypes();
        foreach ($resources as $resourceInfo) {
            $resourceClass = $resourceInfo->getClassName();

            if (!class_exists($resourceClass)) {
                throw new InternalServerErrorException('Service configuration class name lookup failed for resource ' .
                    $resourceClass);
            }

            $resourceName = $resourceInfo->getName();
            if (Session::checkForAnyServicePermissions($this->name, $resourceName)) {
                /** @type BaseSystemResource $resourceClass */
                $results = $resourceClass::getApiDocInfo($this, $resourceInfo);
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