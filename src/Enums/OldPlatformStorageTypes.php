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
namespace DreamFactory\Rave\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * OldPlatformStorageTypes from 1.x - Use for DB conversion only!
 */
class OldPlatformStorageTypes extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const AWS_S3 = 0;
    /**
     * @var int
     */
    const AWS_DYNAMODB = 1;
    /**
     * @var int
     */
    const AWS_SIMPLEDB = 2;
    /**
     * @var int
     */
    const AZURE_BLOB = 3;
    /**
     * @var int
     */
    const AZURE_TABLES = 4;
    /**
     * @var int
     */
    const COUCHDB = 5;
    /**
     * @var int
     */
    const MONGODB = 6;
    /**
     * @var int
     */
    const OPENSTACK_OBJECT_STORAGE = 7;
    /**
     * @var int
     */
    const RACKSPACE_CLOUDFILES = 8;
    /**
     * @var int
     */
    const AWS_SNS = 9;
}
