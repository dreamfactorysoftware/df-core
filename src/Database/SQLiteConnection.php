<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\Schema\Sqlite\Schema as SqliteSchema;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

class SQLiteConnection extends \Illuminate\Database\SQLiteConnection implements ConnectionInterface
{
    use ConnectionExtension;

    public static function checkRequirements()
    {
        if (!extension_loaded('sqlite3')) {
            throw new \Exception("Required extension 'sqlite3' is not detected, but may be compiled in.");
        }

        static::checkForPdoDriver('sqlite');
    }

    public static function getDriverLabel()
    {
        return 'SQLite';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-sqlite.connection.php
        return 'sqlite:db.sq3';
    }

    public static function adaptConfig(array &$config)
    {
        $dsn = isset($config['dsn']) ? $config['dsn'] : null;
        if (!empty($dsn)) {
            if (!isset($config['database'])) {
                $file = substr($dsn, 7);
                if (false === strpos($file, DIRECTORY_SEPARATOR)) {
                    // no directories involved, store it where we want to store it
                    $storage = config('df.db.sqlite_storage');
                    if (!is_dir($storage)) {
                        // Attempt
                        @mkdir($storage);
                    }
                    if (!is_dir($storage)) {
                        logger('Failed to access storage path ' . $storage);
                        throw new InternalServerErrorException('Failed to access storage path.');
                    }

                    $file = rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
                }
                $config['database'] = $file;
            }
        }

        // laravel database config requires options to be [], not null
        if (array_key_exists('options', $config) && is_null($config['options'])) {
            $config['options'] = [];
        }
    }

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        $this->getPdo()->exec('PRAGMA foreign_keys=1');
    }

    public function getSchema()
    {
        if ($this->schemaExtension === null) {
            $this->schemaExtension = new SqliteSchema($this);
        }

        return $this->schemaExtension;
    }

    /**
     * @return boolean
     */
    public function supportsFunctions()
    {
        return false;
    }

    /**
     * @return boolean
     */
    public function supportsProcedures()
    {
        return false;
    }
}
