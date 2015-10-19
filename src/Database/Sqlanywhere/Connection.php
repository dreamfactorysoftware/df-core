<?php
namespace DreamFactory\Core\Database\Sqlanywhere;

/**
 * Connection represents a connection to a SAP SQL Anywhere database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{
    public static function checkRequirements($driver, $throw_exception = true)
    {
        $extension = 'mssql';
        if (!extension_loaded($extension)) {
            if ($throw_exception) {
                throw new \Exception("Required extension or module 'mssql' is not installed or loaded.");
            } else {
                return false;
            }
        }

        return parent::checkRequirements('dblib', $throw_exception);
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

    public function __construct($dsn = '', $username = '', $password = '')
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

        parent::__construct($dsn, $username, $password);
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}