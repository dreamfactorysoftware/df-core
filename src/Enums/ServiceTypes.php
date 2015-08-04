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
}
