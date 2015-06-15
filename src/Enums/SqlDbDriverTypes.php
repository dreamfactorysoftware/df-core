<?php
namespace DreamFactory\Core\Enums;

use DreamFactory\Library\Utility\Enums\FactoryEnum;

/**
 * SqlDbDriverTypes
 * SQL Database driver string constants
 */
class SqlDbDriverTypes extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var int
     */
    const DRV_OTHER = 0;
    /**
     * @var int
     */
    const DRV_SQLSRV = 1;
    /**
     * @var int
     */
    const DRV_MYSQL = 2;
    /**
     * @var int
     */
    const DRV_SQLITE = 3;
    /**
     * @var int
     */
    const DRV_PGSQL = 4;
    /**
     * @var int
     */
    const DRV_OCSQL = 5;
    /**
     * @var int
     */
    const DRV_DBLIB = 6;
    /**
     * @var int
     */
    const DRV_IBMDB2 = 7;

    /**
     * @var string
     */
    const MS_SQL = 'mssql';
    /**
     * @var string
     */
    const DBLIB = 'dblib';
    /**
     * @var string
     */
    const SQL_SERVER = 'sqlsrv';
    /**
     * @var string
     */
    const MYSQL = 'mysql';
    /**
     * @var string
     */
    const MYSQLI = 'mysqli';
    /**
     * @var string
     */
    const SQLITE = 'sqlite';
    /**
     * @var string
     */
    const SQLITE2 = 'sqlite2';
    /**
     * @var string
     */
    const ORACLE = 'oci';
    /**
     * @var string
     */
    const POSTGRESQL = 'pgsql';
    /**
     * @var string
     */
    const IBMDB2 = 'ibm';

    /**
     * Returns the PDO driver type for the given connection's driver name
     *
     * @param string $driverType
     *
     * @return int
     */
    public static function driverType($driverType)
    {
        switch ($driverType) {
            case static::MS_SQL:
            case static::SQL_SERVER:
                return static::DRV_SQLSRV;

            case static::DBLIB:
                return static::DRV_DBLIB;

            case static::MYSQL:
            case static::MYSQLI:
                return static::DRV_MYSQL;

            case static::SQLITE:
            case static::SQLITE2:
                return static::DRV_SQLITE;

            case static::ORACLE:
                return static::DRV_OCSQL;

            case static::POSTGRESQL:
                return static::DRV_PGSQL;

            case static::IBMDB2:
                return static::DRV_IBMDB2;

            default:
                return static::DRV_OTHER;
        }
    }
}
