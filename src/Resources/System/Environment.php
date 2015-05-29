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

class Environment extends ReadOnlySystemResource
{
    protected function handleGET()
    {
        $_release = null;
        $_phpInfo = $this->_getPhpInfo();

        if ( false !== ( $_raw = file( static::LSB_RELEASE ) ) && !empty( $_raw ) )
        {
            $_release = array();

            foreach ( $_raw as $_line )
            {
                $_fields = explode( '=', $_line );
                $_release[str_replace( 'distrib_', null, strtolower( $_fields[0] ) )] = trim( $_fields[1], PHP_EOL . '"' );
            }
        }

        $_response = array(
            'php_info' => $_phpInfo,
            'platform' => Config::getCurrentConfig(),
            'release'  => $_release,
            'server'   => array(
                'server_os' => strtolower( php_uname( 's' ) ),
                'uname'     => php_uname( 'a' ),
            ),
        );

        array_multisort( $_response );

        //	Cache configuration
        Platform::storeSet( static::CACHE_KEY, $_response, static::CONFIG_CACHE_TTL );

        $this->_response = $this->_response ? array_merge( $this->_response, $_response ) : $_response;
        unset( $_response );

        return $this->_response;
    }

    /**
     * Parses the data coming back from phpinfo() call and returns in an array
     *
     * @return array
     */
    protected function _getPhpInfo()
    {
        $_html = null;
        $_info = array();
        $_pattern =
            '#(?:<h2>(?:<a name=".*?">)?(.*?)(?:</a>)?</h2>)|(?:<tr(?: class=".*?")?><t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>(?:<t[hd](?: class=".*?")?>(.*?)\s*</t[hd]>)?)?</tr>)#s';

        \ob_start();
        @\phpinfo();
        $_html = \ob_get_contents();
        \ob_end_clean();

        if ( preg_match_all( $_pattern, $_html, $_matches, PREG_SET_ORDER ) )
        {
            foreach ( $_matches as $_match )
            {
                $_keys = array_keys( $_info );
                $_lastKey = end( $_keys );

                if ( strlen( $_match[1] ) )
                {
                    $_info[$_match[1]] = array();
                }
                elseif ( isset( $_match[3] ) )
                {
                    $_info[$_lastKey][$_match[2]] = isset( $_match[4] ) ? array($_match[3], $_match[4]) : $_match[3];
                }
                else
                {
                    $_info[$_lastKey][] = $_match[2];
                }

                unset( $_keys, $_match );
            }
        }

        return $this->_cleanPhpInfo( $_info );
    }

    /**
     * @param array $info
     *
     * @param bool  $recursive
     *
     * @return array
     */
    protected function _cleanPhpInfo( $info, $recursive = false )
    {
        static $_excludeKeys = array('directive', 'variable',);

        $_clean = array();

        //  Remove images and move nested args to root
        if ( !$recursive && isset( $info[0], $info[0][0] ) && is_array( $info[0] ) )
        {
            $info['general'] = array();

            foreach ( $info[0] as $_key => $_value )
            {
                if ( is_numeric( $_key ) || in_array( strtolower( $_key ), $_excludeKeys ) )
                {
                    continue;
                }

                $info['general'][$_key] = $_value;
                unset( $info[0][$_key] );
            }

            unset( $info[0] );
        }

        foreach ( $info as $_key => $_value )
        {
            if ( in_array( strtolower( $_key ), $_excludeKeys ) )
            {
                continue;
            }

            $_key = strtolower( str_replace( ' ', '_', $_key ) );

            if ( is_array( $_value ) && 2 == count( $_value ) && isset( $_value[0], $_value[1] ) )
            {
                $_v1 = Option::get( $_value, 0 );

                if ( $_v1 == '<i>no value</i>' )
                {
                    $_v1 = null;
                }

                if ( Scalar::in( strtolower( $_v1 ), 'on', 'off', '0', '1' ) )
                {
                    $_v1 = Option::getBool( $_value, 0 );
                }

                $_value = $_v1;
            }

            if ( is_array( $_value ) )
            {
                $_value = $this->_cleanPhpInfo( $_value, true );
            }

            $_clean[$_key] = $_value;
        }

        return $_clean;
    }

    public function getApiDocInfo()
    {
        $path = '/' . $this->getServiceName() . '/' . $this->getFullPathName();
        $eventPath = $this->getServiceName() . '.' . $this->getFullPathName( '.' );

        return [

            //-------------------------------------------------------------------------
            //	APIs
            //-------------------------------------------------------------------------

            'apis'   => [
                [
                    'path'        => $path,
                    'operations'  => [
                        [
                            'method'     => 'GET',
                            'summary'    => 'getEnvironment() - Retrieve environment information.',
                            'nickname'   => 'getEnvironment',
                            'type'       => 'EnvironmentResponse',
                            'event_name' => $eventPath . '.read',
                            'notes'      => 'The retrieved information describes the container/machine on which the DSP resides.',
                        ],
                    ],
                    'description' => 'Operations for system configuration options.',
                ],
            ],
            //-------------------------------------------------------------------------
            //	Models
            //-------------------------------------------------------------------------

            'models' => [
                'ServerSection'       => [
                    'id'         => 'ServerSection',
                    'properties' => [
                        'server_os' => [
                            'type' => 'string',
                        ],
                        'uname'     => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'ReleaseSection'      => [
                    'id'         => 'ReleaseSection',
                    'properties' => [
                        'id'          => [
                            'type' => 'string',
                        ],
                        'release'     => [
                            'type' => 'string',
                        ],
                        'codename'    => [
                            'type' => 'string',
                        ],
                        'description' => [
                            'type' => 'string',
                        ],
                    ],
                ],
                'PlatformSection'     => [
                    'id'         => 'PlatformSection',
                    'properties' => [
                        'is_hosted'           => [
                            'type' => 'boolean',
                        ],
                        'is_private'          => [
                            'type' => 'boolean',
                        ],
                        'dsp_version_current' => [
                            'type' => 'string',
                        ],
                        'dsp_version_latest'  => [
                            'type' => 'string',
                        ],
                        'upgrade_available'   => [
                            'type' => 'boolean',
                        ],
                    ],
                ],
                'PhpInfoSection'      => [
                    'id'         => 'PhpInfoSection',
                    'properties' => [
                        'name' => [
                            'type'  => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
                'EnvironmentResponse' => [
                    'id'         => 'EnvironmentResponse',
                    'properties' => [
                        'server'   => [
                            'type' => 'ServerSection',
                        ],
                        'release'  => [
                            'type' => 'ReleaseSection',
                        ],
                        'platform' => [
                            'type' => 'PlatformSection',
                        ],
                        'php_info' => [
                            'type'  => 'array',
                            'items' => [
                                '$ref' => 'PhpInfoSection',
                            ],
                        ],
                    ],
                ],
            ]
        ];
    }
}