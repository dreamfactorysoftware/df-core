<?php
namespace DreamFactory\Core\Enums;


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
