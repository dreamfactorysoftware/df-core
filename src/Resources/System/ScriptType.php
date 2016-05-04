<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\ScriptEngineTypeInterface;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ResourcesWrapper;
use ScriptEngineManager;

class ScriptType extends BaseRestResource
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
            /** @type ScriptEngineTypeInterface $type */
            if (null === $type = ScriptEngineManager::getScriptEngineType($this->resource)) {
                throw new NotFoundException("Script engine type '{$this->resource}' not found.");
            }

            return $type->toArray();
        }

        $resources = [];
        $types = ScriptEngineManager::getScriptEngineTypes();
        /** @type ScriptEngineTypeInterface $type */
        foreach ($types as $type) {
            $resources[] = $type->toArray();
        }

        return ResourcesWrapper::wrapResources($resources);
    }
}