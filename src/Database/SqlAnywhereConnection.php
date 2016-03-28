<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Database\Sqlanywhere\Schema;
use Illuminate\Database\Connection;

class SqlAnywhereConnection extends Connection
{
    use ConnectionExtension;

    public function checkRequirements()
    {
        $extension = 'mssql';
        if (!extension_loaded($extension)) {
            throw new \Exception("Required extension 'mssql' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver('dblib');
    }

    public static function getDriverLabel()
    {
        return 'SAP SQL Anywhere';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-dblib.connection.php
        return 'dblib:host=localhost:2638;dbname=database';
    }

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
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
        if (null !== $confLocation = config('df.db.freetds.sqlanywhere')) {
            if (!putenv("FREETDSCONF=$confLocation")) {
                \Log::alert('Could not write environment variable for FREETDSCONF location.');
            }
        }

        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}
