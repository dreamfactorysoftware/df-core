<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Resources\System;

use DreamFactory\Rave\Contracts\CachedInterface;
use DreamFactory\Rave\Exceptions\NotImplementedException;
use DreamFactory\Rave\Resources\BaseRestResource;
use DreamFactory\Rave\Utility\CacheUtilities;
use DreamFactory\Rave\Utility\ServiceHandler;

/**
 * Class Cache
 *
 * @package DreamFactory\Rave\Resources
 */
class Cache extends BaseRestResource
{
    /**
     * Handles DELETE action
     *
     * @return array
     * @throws NotImplementedException
     */
    protected function handleDELETE()
    {
        if ( empty( $this->resource ) )
        {
            CacheUtilities::flush();
        }
        else
        {
            $service = ServiceHandler::getService($this->resource);
            if ($service instanceof CachedInterface)
            {
                $service->flush();
            }
            else
            {
                throw new NotImplementedException( 'Service does not implement API controlled cache.');
            }
        }

        return ['success' => true];
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName( '.' );
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
                        'parameters'       => [ ],
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
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
                        'responseMessages' => [
                            [
                                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
                                'code'    => 400,
                            ],
                            [
                                'message' => 'Unauthorized Access - No currently valid session available.',
                                'code'    => 401,
                            ],
                            [
                                'message' => 'System Error - Specific reason is included in the error message.',
                                'code'    => 500,
                            ],
                        ],
                        'notes'            => 'This clears all cached information related to a particular service. Doing so may impact the performance of the service.',
                    ],
                ],
                'description' => "Operations for individual service-related cache administration.",
            ],
        ];

        return [ 'apis' => $apis, 'models' => [] ];
    }
}