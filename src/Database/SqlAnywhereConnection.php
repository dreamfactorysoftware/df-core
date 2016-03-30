<?php

namespace DreamFactory\Core\Database;

use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as DoctrineDriver;
use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Query\Grammars\SqlAnywhereGrammar as QueryGrammar;
use DreamFactory\Core\Database\Query\Processors\SqlAnywhereProcessor;
use DreamFactory\Core\Database\Schema\Grammars\SqlAnywhereGrammar as SchemaGrammar;
use DreamFactory\Core\Database\Sqlanywhere\Schema as SqlanywhereSchema;
use Illuminate\Database\Connection;

class SqlAnywhereConnection extends Connection implements ConnectionInterface
{
    use ConnectionExtension;

    public static function checkRequirements()
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
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new SqlAnywhereSchema($this);
        }

        return $this->schemaExtension;
    }

    /**
     * Get the default query grammar instance.
     *
     * @return QueryGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return SchemaGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the default post processor instance.
     *
     * @return SqlAnywhereProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new SqlAnywhereProcessor;
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \Doctrine\DBAL\Driver\PDOSqlsrv\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}
