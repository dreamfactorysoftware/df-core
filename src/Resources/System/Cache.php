<?php

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Exceptions\NotImplementedException;
use DreamFactory\Core\Models\ServiceCacheConfig;
use DreamFactory\Core\Resources\BaseRestResource;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\ArrayUtils;

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
    protected static function getResourceIdentifier()
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
            foreach ($cacheables as $cacheable) {
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

    public static function getApiDocInfo(\DreamFactory\Core\Models\Service $service, array $resource = [])
    {
        $serviceName = strtolower($service->name);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(ArrayUtils::get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $eventPath = $serviceName . '.' . $resourceName;

        $apis = [
            $path                => [
                'delete' => [
                    'tags'              => [$serviceName],
                    'summary'           => 'deleteAllCache() - Delete all cache.',
                    'operationId'       => 'deleteAllCache',
                    'x-publishedEvents' => [$eventPath . '.delete'],
                    'parameters'        => [],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'This clears all cached information in the system. Doing so may impact the performance of the system.',
                ],
            ],
            $path . '/{service}' => [
                'delete' => [
                    'tags'              => [$serviceName],
                    'summary'           => 'deleteServiceCache() - Delete cache for one service.',
                    'operationId'       => 'deleteServiceCache',
                    'x-publishedEvents' => [$eventPath . '{service}.delete'],
                    'parameters'        => [
                        [
                            'name'        => 'service',
                            'description' => 'Identifier of the service whose cache we are to delete.',
                            'type'        => 'string',
                            'in'          => 'path',
                            'required'    => true,
                        ],
                    ],
                    'responses'         => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/Success']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description'       => 'This clears all cached information related to a particular service. Doing so may impact the performance of the service.',
                ],
            ],
        ];

        return ['paths' => $apis, 'definitions' => []];
    }
}