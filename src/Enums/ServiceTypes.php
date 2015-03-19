<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace DreamFactory\Rave\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * ServiceTypes
 */
class ServiceTypes extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    const __default = self::UNKNOWN;

    /**
     * @var int
     */
    const UNKNOWN = '';

    /* Singular Composer and System Provisioned Services */

    /**
     * @var int
     */
    const API_DOCS = 'api_docs';
    /**
     * @var int
     */
    const SCRIPT = 'script';
    /**
     * @var int
     */
    const USER = 'user';
    /**
     * @var int
     */
    const PORTAL = 'portal';
    /**
     * @var int
     */
    const EMAIL = 'email';
    /**
     * @var int
     */
    const REMOTE_WEB_SERVICE = 'web';
    /**
     * @var int
     */
    const NATIVE_FILE_STORAGE = 'local_file';
    /**
     * @var int
     */
    const NATIVE_SQL_DB = 'local_sql';

    /* Admin User Provisioned Services */

    /* File Storage Services - use 1xx for grouping */

    /**
     * @var int
     */
    const AWS_S3 = 'aws_s3';
    /**
     * @var int
     */
    const AZURE_BLOB = 'azure_blob';
    /**
     * @var int
     */
    const OPENSTACK_OBJECT_STORAGE = 'openstack_object';
    /**
     * @var int
     */
    const RACKSPACE_CLOUDFILES = 'rackspace_cloudfiles';

    /* SQL Database Services - use 2xx fro grouping */

    /**
     * @var int
     */
    const SQL_DB = 'sql_db';
    /**
     * @var int
     */
    const SALESFORCE = 'salesforce';

    /* NoSQL Database Services  - use 3xx for grouping */

    /**
     * @var int
     */
    const AWS_DYNAMODB = 'aws_dynamodb';
    /**
     * @var int
     */
    const AWS_SIMPLEDB = 'aws_simpledb';
    /**
     * @var int
     */
    const AZURE_TABLES = 'azure_table';
    /**
     * @var int
     */
    const COUCHDB = 'couchdb';
    /**
     * @var int
     */
    const MONGODB = 'mongodb';

    /* Push Notification Services - use 4xx for grouping */

    /**
     * @var int
     */
    const AWS_SNS = 'aws_sns';

    //*************************************************************************
    //	Members
    //*************************************************************************
    /**
     * @param int    $value        enumerated type value
     * @param string $service_name given name of the service, also returned as default
     *
     * @var array A map of classes for services
     */
    protected static $_classMap = array(
        self::USER                     => 'UserManager',
        self::PORTAL                   => 'PortalSvc',
        self::EMAIL                    => 'EmailSvc',
        self::SCRIPT                   => 'Script',
        self::REMOTE_WEB_SERVICE       => 'RemoteWebSvc',
        self::NATIVE_FILE_STORAGE      => 'LocalFileSvc',
        self::NATIVE_SQL_DB            => 'SqlDbSvc',
        /* File Storage => 'RemoteFileSvc' */
        self::AWS_S3                   => 'AwsS3Svc',
        self::AZURE_BLOB               => 'WindowsAzureBlobSvc',
        self::OPENSTACK_OBJECT_STORAGE => 'OpenStackObjectStoreSvc',
        self::RACKSPACE_CLOUDFILES     => 'OpenStackObjectStoreSvc',
        /* Database => 'BaseDbSvc' */
        self::SQL_DB                   => 'SqlDbSvc',
        self::SALESFORCE               => 'SalesforceDbSvc',
        /* NOSQL Database => 'NoSqlDbSvc' */
        self::AWS_DYNAMODB             => 'AwsDynamoDbSvc',
        self::AWS_SIMPLEDB             => 'AwsSimpleDbSvc',
        self::AZURE_TABLES             => 'WindowsAzureTablesSvc',
        self::COUCHDB                  => 'CouchDbSvc',
        self::MONGODB                  => 'MongoDbSvc',
        /* PUSH_SERVICE => 'BasePushSvc' */
        self::AWS_SNS                  => 'AwsSnsSvc',
    );

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param int $type enumerated type value
     *
     * @return string - associated file name of native service, or false if not defined
     */
    public static function getFileName( $type )
    {
        if ( isset( static::$_classMap[$type] ) )
        {
            return static::$_classMap[$type];
        }

        return false;
    }
}
