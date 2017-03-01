<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\ServiceTypeInterface;
use DreamFactory\Core\Enums\ApiOptions;
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
            /** @type ServiceTypeInterface $type */
            if (null === $type = ServiceManager::getServiceType($this->resource)) {
                throw new NotFoundException("Service type '{$this->resource}' not found.");
            }

            return $type->toArray();
        }

        $resources = [];
        $group = $this->request->getParameter('group');
        $types = ServiceManager::getServiceTypes($group);
        /** @type ServiceTypeInterface $type */
        foreach ($types as $type) {
            $resources[] = $type->toArray();
        }

        $asList = $this->request->getParameterAsBool(ApiOptions::AS_LIST);
        $idField = $this->request->getParameter(ApiOptions::ID_FIELD, static::getResourceIdentifier());
        $fields = $this->request->getParameter(ApiOptions::FIELDS, ApiOptions::FIELDS_ALL);

        return ResourcesWrapper::cleanResources($resources, $asList, $idField, $fields);
    }
}