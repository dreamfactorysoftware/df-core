<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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
namespace DreamFactory\Core\Services;

use DreamFactory\Core\Components\ApiDocManager;
use DreamFactory\Core\Contracts\CachedInterface;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Utility\CacheUtilities;
use DreamFactory\Core\Utility\Session;

/**
 * Swagger
 * DSP API Documentation manager
 *
 */
class Swagger extends BaseRestService implements CachedInterface
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string The current API version
     */
    const API_VERSION = '2.0';
    /**
     * @const string The Swagger version
     */
    const SWAGGER_VERSION = '1.2';
    /**
     * @const string The private cache file
     */
    const SWAGGER_CACHE_FILE = '_.json';
    /**
     * @const integer How long a swagger cache will live, 1440 = 24 minutes (default session timeout).
     */
    const SWAGGER_CACHE_TTL = 1440;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return array|string|bool
     */
    protected function handleGET()
    {
        // lock down access to valid apps only, can't check session permissions
        // here due to sdk access
//        Session::checkAppPermission( null, false );
        if ( $this->request->getParameterAsBool( 'refresh' ) )
        {
            $this->flush();
        }

        if ( empty( $this->resource ) )
        {
            return $this->getSwagger();
        }

        return $this->getSwaggerForService( $this->resource );
    }

    /**
     * Main retrieve point for a list of swagger-able services
     * This builds the full swagger cache if it does not exist
     *
     * @return string The JSON contents of the swagger api listing.
     * @throws InternalServerErrorException
     */
    public function getSwagger()
    {
        $roleId = Session::getRoleId();
        if ( null === ( $content = CacheUtilities::getByRoleId( $roleId, static::SWAGGER_CACHE_FILE ) ) )
        {
            \Log::info( 'Building Swagger cache' );

            //  Build services from database
            //  Pull any custom swagger docs
            $result = Service::all( [ 'name', 'description' ] );

            // gather the services
            $services = [ ];

            //	Spin through services and pull the configs
            foreach ( $result as $service )
            {
                // build main services list
                $services[] = [
                    'path'        => '/' . $service->name,
                    'description' => $service->description
                ];

                unset( $service );
            }

            // cache main api listing file
            $description = <<<HTML
HTML;

            $resourceListing = [
                'swaggerVersion' => static::SWAGGER_VERSION,
                'apiVersion'     => \Config::get( 'df.api_version', static::API_VERSION ),
                'authorizations' => [ 'apiKey' => [ 'type' => 'apiKey', 'passAs' => 'header' ] ],
                'info'           => [
                    'title'       => 'DreamFactory Live API Documentation',
                    'description' => $description,
                    //'termsOfServiceUrl' => 'http://www.dreamfactory.com/terms/',
                    'contact'     => 'support@dreamfactory.com',
                    'license'     => 'Apache 2.0',
                    'licenseUrl'  => 'http://www.apache.org/licenses/LICENSE-2.0.html'
                ],
                /**
                 * The events thrown that are relevant to Swagger
                 */
                'events'         => [ ],
            ];

            $content = array_merge( $resourceListing, [ 'apis' => $services ] );
            $content = json_encode( $content, JSON_UNESCAPED_SLASHES );

            if ( false === CacheUtilities::putByRoleId( $roleId, static::SWAGGER_CACHE_FILE, $content, static::SWAGGER_CACHE_TTL ) )
            {
                \Log::error( '  * System error creating swagger cache file: ' . static::SWAGGER_CACHE_FILE );
            }

            // Add to this services keys for clearing later.
            $key = CacheUtilities::makeKeyFromTypeAndId( 'role', $roleId, static::SWAGGER_CACHE_FILE );
            CacheUtilities::addKeysByTypeAndId( 'service', $this->id, $key );

            \Log::info( 'Swagger cache build process complete' );
        }

        return $content;
    }

    /**
     * Main retrieve point for each service
     *
     * @param string $name Which service (name) to retrieve.
     *
     * @return string
     * @throws NotFoundException
     */
    public function getSwaggerForService( $name )
    {
        $_cachePath = $name . '.json';

        if ( null === $_content = CacheUtilities::getByServiceId( $this->id, $_cachePath ) )
        {
            $service = Service::whereName( $name )->get()->first();
            if ( empty( $service ) )
            {
                throw new NotFoundException( "Could not find a service for '$name''" );
            }

            $_content = ApiDocManager::getStoredContentForService( $service );

            if ( empty( $_content ) )
            {
                throw new NotFoundException( "No Swagger content found for service '$name'" );
            }

            $_baseSwagger = [
                'swaggerVersion' => static::SWAGGER_VERSION,
                'apiVersion'     => \Config::get( 'df.api_version', static::API_VERSION ),
                'basePath'       => url( '/api/v2' ),
            ];

            $_content = array_merge( $_baseSwagger, $_content );
            $_content = json_encode( $_content, JSON_UNESCAPED_SLASHES );

            // replace service type placeholder with api name for this service instance
            $_content = str_replace( '{api_name}', $name, $_content );

            // cache it for later access
            if ( false === CacheUtilities::putByServiceId( $this->id, $_cachePath, $_content, static::SWAGGER_CACHE_TTL ) )
            {
                \Log::error( "  * System error creating swagger cache file: $name.json" );
            }

            // Add to this the queried service's keys for clearing later.
            $key = CacheUtilities::makeKeyFromTypeAndId( 'service', $this->id, $_cachePath );
            CacheUtilities::addKeysByTypeAndId( 'service', $service->id, $key );
        }

        return $_content;
    }

    /**
     * Clears the cache produced by the swagger annotations
     */
    public function flush()
    {
        CacheUtilities::forgetAllByTypeAndId( 'service', $this->id );

        ApiDocManager::clearCache();
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->name;
        $eventPath = $this->name;
        $apis = [
            [
                'path'        => $path,
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getApiDocs() - Retrieve the base Swagger document.',
                        'nickname'         => 'getApiDocs',
                        'type'             => 'ApiDocsResponse',
                        'event_name'       => $eventPath . '.list',
                        'consumes'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'produces'         => [ 'application/json', 'application/xml', 'text/csv' ],
                        'parameters'       => [
                            [
                                'name'          => 'file',
                                'description'   => 'Download the results of the request as a file.',
                                'allowMultiple' => false,
                                'type'          => 'string',
                                'paramType'     => 'query',
                                'required'      => false,
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
                        'notes'            => 'This returns the base Swagger file containing all API services.',
                    ],
                ],
                'description' => 'Operations for retrieving API documents.',
            ],
            [
                'path'        => $path . '/{id}',
                'operations'  => [
                    [
                        'method'           => 'GET',
                        'summary'          => 'getApiDoc() - Retrieve one API document.',
                        'nickname'         => 'getApiDoc',
                        'type'             => 'ApiDocResponse',
                        'event_name'       => $eventPath . '.read',
                        'parameters'       => [
                            [
                                'name'          => 'id',
                                'description'   => 'Identifier of the API document to retrieve.',
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
                        'notes'            => '',
                    ],
                ],
                'description' => 'Operations for individual API documents.',
            ],
        ];

        $models = [
            'ApiDocsResponse' => [
                'id'         => 'ApiDocsResponse',
                'properties' => [
                    'apiVersion'     => [
                        'type'        => 'string',
                        'description' => 'Version of the API.',
                    ],
                    'swaggerVersion' => [
                        'type'        => 'string',
                        'description' => 'Version of the Swagger API.',
                    ],
                    'apis'           => [
                        'type'        => 'array',
                        'description' => 'Array of APIs.',
                        'items'       => [
                            '$ref' => 'Api',
                        ],
                    ],
                ],
            ],
            'ApiDocResponse'  => [
                'id'         => 'ApiDocResponse',
                'properties' => [
                    'apiVersion'     => [
                        'type'        => 'string',
                        'description' => 'Version of the API.',
                    ],
                    'swaggerVersion' => [
                        'type'        => 'string',
                        'description' => 'Version of the Swagger API.',
                    ],
                    'basePath'       => [
                        'type'        => 'string',
                        'description' => 'Base path of the API.',
                    ],
                    'apis'           => [
                        'type'        => 'array',
                        'description' => 'Array of APIs.',
                        'items'       => [
                            '$ref' => 'Api',
                        ],
                    ],
                    'models'         => [
                        'type'        => 'array',
                        'description' => 'Array of API models.',
                        'items'       => [
                            '$ref' => 'Model',
                        ],
                    ],
                ],
            ],
            'Api'             => [
                'id'         => 'Api',
                'properties' => [
                    'path'        => [
                        'type'        => 'string',
                        'description' => 'Path to access the API.',
                    ],
                    'description' => [
                        'type'        => 'string',
                        'description' => 'Description of the API.',
                    ],
                ],
            ],
            'Model'           => [
                'id'         => 'Model',
                'properties' => [
                    '__name__' => [
                        'type'        => 'string',
                        'description' => 'Model Definition.',
                    ],
                ],
            ],
        ];

        return [ 'apis' => $apis, 'models' => $models ];
    }
}
