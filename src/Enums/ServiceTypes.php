<?php
namespace DreamFactory\Core\Enums;

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
    protected static $classMap = array(
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
    public static function getFileName($type)
    {
        if (isset(static::$classMap[$type])) {
            return static::$classMap[$type];
        }

        return false;
    }
}
