<?php

namespace DreamFactory\Core\Services;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Resources\BaseDbResource;

abstract class BaseDbService extends BaseRestService
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array Array of resource defining arrays
     */
    protected $resources = [];

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * {@inheritdoc}
     */
    public function getResources($only_handlers = false)
    {
        if (!$only_handlers) {
            if ($this->request->getParameterAsBool(ApiOptions::AS_ACCESS_LIST)) {
                $refresh = $this->request->getParameterAsBool(ApiOptions::REFRESH);
                $schema = $this->request->getParameter(ApiOptions::SCHEMA, '');

                $output = [];
                $access = $this->getPermissions();
                if (!empty($access)) {
                    $output[] = '';
                    $output[] = '*';
                }
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
                        $output[] = $resource->name . '/';
                        $output[] = $resource->name . '/*';

                        $results = $resource->listAccessComponents($schema, $refresh);
                        $output = array_merge($output, $results);
                    }
                }

                return $output;
            } else {
                return array_values($this->resources);
            }
        }

        return $this->resources;
    }
}