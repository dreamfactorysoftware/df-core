<?php
namespace DreamFactory\Core\Enums;


/**
 * OldPlatformServiceTypes from 1.x - Use for DB conversion only!
 */
class OldPlatformServiceTypes extends FactoryEnum
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const SYSTEM_SERVICE = 0x0000;
    /**
     * @var int
     */
    const EMAIL_SERVICE = 0x0001;
    /**
     * @var int
     */
    const LOCAL_FILE_STORAGE = 0x0002;
    /**
     * @var int
     */
    const LOCAL_SQL_DB = 0x0004;
    /**
     * @var int - Deprecated! - use NATIVE_SQL_DB
     */
    const LOCAL_SQL_DB_SCHEMA = 0x0008;
    /**
     * @var int
     */
    const NOSQL_DB = 0x0010;
    /**
     * @var int
     */
    const SALESFORCE = 0x0020;
    /**
     * @var int
     */
    const LOCAL_PORTAL_SERVICE = 0x0040;
    /**
     * @var int
     */
    const REMOTE_FILE_STORAGE = 0x1002;
    /**
     * @var int
     */
    const REMOTE_SQL_DB = 0x1004;
    /**
     * @var int - Deprecated! - use SQL_DB
     */
    const REMOTE_SQL_DB_SCHEMA = 0x1008;
    /**
     * @var int
     */
    const REMOTE_WEB_SERVICE = 0x1020;
    /**
     * @var int
     */
    const SCRIPT_SERVICE = 0x1040;
    /**
     * @var int
     */
    const PUSH_SERVICE = 0x1080;
}
