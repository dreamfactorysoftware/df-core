<?php

namespace DreamFactory\Core\Database;

use DreamFactory\Core\Database\Sqlite\Schema;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

class SQLiteConnection extends \Illuminate\Database\SQLiteConnection
{
    use ConnectionExtension;

    public $initSQLs = ['PRAGMA foreign_keys=1'];

    public function checkRequirements()
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

    public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
    {
        $dsn = isset($config['dsn']) ? $config['dsn'] : null;
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

            $config['dsn'] = 'sqlite:' . rtrim($storage, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
        }

        parent::__construct($pdo, $database, $tablePrefix, $config);
    }

    public static function adaptConfig(array &$config)
    {
        parent::adaptConfig($config);
        $dsn = isset($config['dsn']) ? $config['dsn'] : null;
        if (!empty($dsn)) {
            if (!isset($config['database'])) {
                $config['database'] = substr($dsn, 7);
            }
        }
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}
