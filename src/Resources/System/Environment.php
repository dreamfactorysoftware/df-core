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

use DreamFactory\Rave\Resources\BaseRestSystemResource;

class Environment extends BaseRestSystemResource
{

    public function __construct( $settings = array() )
    {
        parent::__construct( $settings );
    }

    protected function handleGET()
    {
        return false;
    }

    protected function handlePOST()
    {
        return false;
    }

    public function getApiDocInfo()
    {
        return array(

            //-------------------------------------------------------------------------
            //	APIs
            //-------------------------------------------------------------------------

            'apis'   => array(
                array(
                    'path'        => '/{api_name}/environment',
                    'operations'  => array(
                        array(
                            'method'     => 'GET',
                            'summary'    => 'getEnvironment() - Retrieve environment information.',
                            'nickname'   => 'getEnvironment',
                            'type'       => 'EnvironmentResponse',
                            'event_name' => '{api_name}.environment.read',
                            'notes'      => 'The retrieved information describes the container/machine on which the DSP resides.',
                        ),
                    ),
                    'description' => 'Operations for system configuration options.',
                ),
            ),
            //-------------------------------------------------------------------------
            //	Models
            //-------------------------------------------------------------------------

            'models' => array(
                'ServerSection'       => array(
                    'id'         => 'ServerSection',
                    'properties' => array(
                        'server_os' => array(
                            'type' => 'string',
                        ),
                        'uname'     => array(
                            'type' => 'string',
                        ),
                    ),
                ),
                'ReleaseSection'      => array(
                    'id'         => 'ReleaseSection',
                    'properties' => array(
                        'id'          => array(
                            'type' => 'string',
                        ),
                        'release'     => array(
                            'type' => 'string',
                        ),
                        'codename'    => array(
                            'type' => 'string',
                        ),
                        'description' => array(
                            'type' => 'string',
                        ),
                    ),
                ),
                'PlatformSection'     => array(
                    'id'         => 'PlatformSection',
                    'properties' => array(
                        'is_hosted'           => array(
                            'type' => 'boolean',
                        ),
                        'is_private'          => array(
                            'type' => 'boolean',
                        ),
                        'dsp_version_current' => array(
                            'type' => 'string',
                        ),
                        'dsp_version_latest'  => array(
                            'type' => 'string',
                        ),
                        'upgrade_available'   => array(
                            'type' => 'boolean',
                        ),
                    ),
                ),
                'PhpInfoSection'      => array(
                    'id'         => 'PhpInfoSection',
                    'properties' => array(
                        'name' => array(
                            'type'  => 'array',
                            'items' => array(
                                'type' => 'string',
                            ),
                        ),
                    ),
                ),
                'EnvironmentResponse' => array(
                    'id'         => 'EnvironmentResponse',
                    'properties' => array(
                        'server'   => array(
                            'type' => 'ServerSection',
                        ),
                        'release'  => array(
                            'type' => 'ReleaseSection',
                        ),
                        'platform' => array(
                            'type' => 'PlatformSection',
                        ),
                        'php_info' => array(
                            'type'  => 'array',
                            'items' => array(
                                '$ref' => 'PhpInfoSection',
                            ),
                        ),
                    ),
                ),
            )
        );
    }
}