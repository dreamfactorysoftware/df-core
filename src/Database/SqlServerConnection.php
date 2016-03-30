<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Mssql\Schema as SqlSrvSchema;

class SqlServerConnection extends \Illuminate\Database\SqlServerConnection implements ConnectionInterface
{
    use ConnectionExtension;

    // These are on by default for sqlsrv driver, but not dblib.
    // Also, can't use 'SET ANSI_DEFAULTS ON', seems to return false positives for DROP TABLE etc. todo
    public $initSQLs = ['SET QUOTED_IDENTIFIER ON;', 'SET ANSI_WARNINGS ON;', 'SET ANSI_NULLS ON;'];

    public static function checkRequirements()
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            $driver = 'sqlsrv';
            $extension = 'sqlsrv';
        } else {
            $driver = 'dblib';
            $extension = 'mssql';
        }

        if (!extension_loaded($extension)) {
            throw new \Exception("Required extension '$extension' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver($driver);
    }

    public static function getDriverLabel()
    {
        return 'SQL Server';
    }
    
    public static function getSampleDsn()
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
            // http://php.net/manual/en/ref.pdo-sqlsrv.connection.php
            return 'sqlsrv:Server=localhost,1433;Database=db';
        }

        // http://php.net/manual/en/ref.pdo-dblib.connection.php
        return 'dblib:host=localhost:1433;dbname=database;charset=UTF-8';
    }
    
    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        if (substr(PHP_OS, 0, 3) == 'WIN') {
        } else {
            if (null !== $dumpLocation = config('df.db.freetds.dump')) {
                if (!putenv("TDSDUMP=$dumpLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMP location.');
                }
            }
            if (null !== $dumpConfLocation = config('df.db.freetds.dumpconfig')) {
                if (!putenv("TDSDUMPCONFIG=$dumpConfLocation")) {
                    \Log::alert('Could not write environment variable for TDSDUMPCONFIG location.');
                }
            }
            if (null !== $confLocation = config('df.db.freetds.sqlsrv')) {
                if (!putenv("FREETDSCONF=$confLocation")) {
                    \Log::alert('Could not write environment variable for FREETDSCONF location.');
                }
            }
        }

        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new SqlSrvSchema($this);
        }

        return $this->schemaExtension;
    }
}
