<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Models\SystemResource;
use DreamFactory\Library\Utility\Inflector;

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

    public static function getApiDocInfo(Service $service)
    {
        $base = parent::getApiDocInfo($service);

        $apis = [];
        $models = [];
        $resources = SystemResource::all()->toArray();
        foreach ($resources as $resourceInfo) {
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