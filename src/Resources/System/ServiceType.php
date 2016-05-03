<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResourcesWrapper;
use ServiceManager;

class ServiceType extends BaseRestResource
{
    /**
     * {@inheritdoc}
     */
    protected static function getResourceIdentifier()
    {
        return 'name';
    }

    /**
     * Handles GET action
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected function handleGET()
    {
        if (!empty($this->resource)) {
            if (null === $type = ServiceManager::getServiceType($this->resource)) {
                throw new NotFoundException("Service type '{$this->resource}' not found.");
            }

            return $type->toArray();
        }

        $resources = [];
        $types = ServiceManager::getServicesTypes();
        /** @type ServiceTypeInterface $type */
        foreach ($types as $type) {
            $resources[] = $type->toArray();
        }

        return ResourcesWrapper::wrapResources($resources);
    }
}