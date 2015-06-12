<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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

namespace DreamFactory\Core\Contracts;

/**
 * Interface ServiceConfigHandlerInterface
 *
 * @package DreamFactory\Core\Contracts
 */
interface ServiceConfigHandlerInterface
{
    /**
     * @param array $config The configuration to be handled
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     *
     * @return boolean Returns true is config is valid, false otherwise.
     */
    public static function validateConfig( $config );

    /**
     * @param int   $id     The Service model key value
     * @param array $config The configuration "field" value to be handled
     *
     * @throws \Exception Detailed exception as to why the config isn't valid.
     */
    public static function setConfig( $id, $config );

    /**
     * @param int $id The Service model key value
     *
     * @return array|null The configuration value retrieved for the service id, or null if not found
     */
    public static function getConfig( $id );

    /**
     * @param int $id The Service model key value
     */
    public static function removeConfig( $id );

    /**
     * @return array|null Returns array of available configurations for this service
     */
    public static function getAvailableConfigs();

    /**
     * @return array|null Returns array of available configuration fields and types for this service
     */
    public static function getConfigSchema();
}