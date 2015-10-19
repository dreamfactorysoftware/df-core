<?php
namespace DreamFactory\Core\Database;

/**
 * Connection represents a connection to a database.
 *
 * ConnectionFactory creates a Connection based on a "driver" type.
 * The "drivers" may not be the direct drivers supported by PHP PDO, but are representative of them.
 *
 */
class ConnectionFactory
{
    /**
     * @var array mapping between database driver and connection class name.
     */
    public static $driverConnectorMap = [
        // PostgreSQL
        'pgsql'       => 'DreamFactory\Core\Database\Pgsql\Connection',
        // MySQL
        'mysql'       => 'DreamFactory\Core\Database\Mysql\Connection',
        // SQLite
        'sqlite'      => 'DreamFactory\Core\Database\Sqlite\Connection',
        // Oracle driver
        'oci'         => 'DreamFactory\Core\Database\Oci\Connection',
        // IBM DB2 driver
        'ibm'         => 'DreamFactory\Core\Database\Ibmdb2\Connection',
        // MS SQL Server on Windows hosts, alias for dblib on Linux, Mac OS X, and maybe others
        'sqlsrv'      => 'DreamFactory\Core\Database\Mssql\Connection',
        // SAP SQL Anywhere alias for dblib on Linux, Mac OS X, and maybe others
        'sqlanywhere' => 'DreamFactory\Core\Database\Sqlanywhere\Connection',
    ];

    /**
     * Returns a list of available PDO drivers.
     *
     * @return array list of available PDO drivers
     * @see http://www.php.net/manual/en/function.PDO-getAvailableDBs.php
     */
    public static function getAvailableDrivers()
    {
        return \PDO::getAvailableDrivers();
    }

    /**
     * Returns a list of available PDO drivers.
     *
     * @return array list of available PDO drivers
     * @see http://www.php.net/manual/en/function.PDO-getAvailableDBs.php
     */
    public static function getAllDrivers()
    {
        $values = [];
        $supported = static::getAvailableDrivers();
        /**
         * @type string     $driver
         * @type Connection $class
         */
        foreach (static::$driverConnectorMap as $driver => $class) {
            $disable = !in_array($driver, $supported);
            $label = $class::getDriverLabel();
            $dsn = $class::getSampleDsn();
            $values[] = ['name' => $driver, 'label' => $label, 'disable' => $disable, 'dsn' => $dsn];
        }

        return $values;
    }

    /**
     * Returns the name of the DB driver from a connection string
     *
     * @param string $dsn The connection string
     *
     * @return string name of the DB driver
     */
    public static function getDriverFromDSN($dsn)
    {
        if (is_string($dsn)) {
            if (($pos = strpos($dsn, ':')) !== false) {
                return strtolower(substr($dsn, 0, $pos));
            }
        }

        return null;
    }

    /**
     * @param string $driver
     *
     * @return bool Returns true if all required extensions are loaded, otherwise an exception is thrown
     * @throws \Exception
     */
    public static function requireDriver($driver)
    {
        if (empty($driver)) {
            throw new \Exception("Database driver name can not be empty.");
        }

        // clients now use indirect association to drivers, dblib can be sqlsrv or sqlanywhere
        if ('dblib' === $driver) {
            $driver = 'sqlsrv';
        }
        /** @type Connection $class */
        if (!empty($class = static::$driverConnectorMap[$driver])) {
            $class::checkRequirements($driver);
        } else {
            throw new \Exception("Driver '$driver' is not supported by this software.");
        }

        return true;
    }

    public static function createConnection($driver, array $config)
    {
        // clients now use indirect association to drivers, dblib can be sqlsrv or sqlanywhere
        if ('dblib' === $driver) {
            $driver = 'sqlsrv';
        }
        if (isset(static::$driverConnectorMap[$driver])) {
            $class = static::$driverConnectorMap[$driver];
            $dsn = isset($config['dsn']) ? $config['dsn'] : null;
            if (empty($dsn)) {
                throw new \InvalidArgumentException('Database connection string (DSN) can not be empty.');
            }

            $user = isset($config['username']) ? $config['username'] : null;
            $password = isset($config['password']) ? $config['password'] : null;

            return new $class($dsn, $user, $password);
        } else {
            throw new \Exception("ConnectionFactory does not support creating connections for '$driver' driver.");
        }
    }
}
