<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Models\ServiceCacheConfig;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ApiDocUtilities;
use DreamFactory\Core\Utility\ServiceHandler;

/**
 * Class Cache
 *
 * @package DreamFactory\Core\Resources
 */
class Cache extends BaseRestResource
{
    /**
     * {@inheritdoc}
     */
    protected function getResourceIdentifier()
    {
        return 'name';
    }

    /**
     * Implement to return the resource configuration for this REST handling object
     *
     * @param boolean $only_handlers
     *
     * @return array Empty when not implemented, otherwise the array of resource information
     */
    public function getResources($only_handlers = false)
    {
        if (!$only_handlers) {
            $resources = [];
            $cacheables = ServiceCacheConfig::with('service')->whereCacheEnabled(true)->get();
            /** @type ServiceCacheConfig $cacheable */
            foreach ($cacheables as $cacheable){
                $resources[] = ['name' => $cacheable->service->name, 'label' => $cacheable->service->label];
            }

            return $resources;
        }

        return [];
    }

    /**
     * Handles DELETE action
     *
     * @return array
     * @throws NotImplementedException
     */
    protected function handleDELETE()
    {
        if (empty($this->resource)) {
            \Cache::flush();
        } else {
            $service = ServiceHandler::getService($this->resource);
            if ($service instanceof CachedInterface) {
                $service->flush();
            } else {
                throw new NotImplementedException('Service does not implement API controlled cache.');
            }
        }

        return ['success' => true];
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName('.');
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteAllCache() - Delete all cache.',
                        'nickname'         => 'deleteAllCache',
                        'type'             => 'Success',
                        'event_name'       => $eventPath . '.delete',
                        'parameters'       => [],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'This clears all cached information in the system. Doing so may impact the performance of the system.',
                    ],
                ],
                'description' => "Operations for global cache administration.",
            ],
            [
                'path'        => $path . '/{service}',
                'operations'  => [
                    [
                        'method'           => 'DELETE',
                        'summary'          => 'deleteServiceCache() - Delete cache for one service.',
                        'nickname'         => 'deleteServiceCache',
                        'type'             => 'Success',
                        'event_name'       => $eventPath . '{service}.delete',
                        'parameters'       => [
                            [
                                'name'          => 'service',
                                'description'   => 'Identifier of the service whose cache we are to delete.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'path',
                                'required'      => true,
                            ],
                        ],
                        'responseMessages' => ApiDocUtilities::getCommonResponses([400, 401, 500]),
                        'notes'            => 'This clears all cached information related to a particular service. Doing so may impact the performance of the service.',
                    ],
                ],
                'description' => "Operations for individual service-related cache administration.",
            ],
        ];

        return ['apis' => $apis, 'models' => []];
    }
}