<?php

namespace DreamFactory\Core\Database\Connectors;

use DreamFactory\Core\Contracts\ConnectionInterface;
use DreamFactory\Core\Database\MySqlConnection;
use DreamFactory\Core\Database\SqlAnywhereConnection;
use DreamFactory\Core\Database\SQLiteConnection;
use DreamFactory\Core\Database\SqlServerConnection;
use DreamFactory\Core\Database\PostgresConnection;
use DreamFactory\Core\Database\OracleConnection;
use DreamFactory\Core\Database\IbmConnection;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Database\Connectors\SQLiteConnector;
use PDO;
use InvalidArgumentException;

class ConnectionFactory extends \Illuminate\Database\Connectors\ConnectionFactory
{
    /**
     * @var array mapping between database driver and connection class name.
     */
    public static $driverConnectorMap = [
        // PostgreSQL
        'pgsql'       => 'DreamFactory\Core\Database\PostgresConnection',
        // MySQL
        'mysql'       => 'DreamFactory\Core\Database\MysqlConnection',
        // SQLite
        'sqlite'      => 'DreamFactory\Core\Database\SQLiteConnection',
        // Oracle driver
        'oci'         => 'DreamFactory\Core\Database\OracleConnection',
        // IBM DB2 driver
        'ibm'         => 'DreamFactory\Core\Database\IbmConnection',
        // MS SQL Server on Windows hosts, alias for dblib on Linux, Mac OS X, and maybe others
        'sqlsrv'      => 'DreamFactory\Core\Database\SqlServerConnection',
        // SAP SQL Anywhere alias for dblib on Linux, Mac OS X, and maybe others
        'sqlanywhere' => 'DreamFactory\Core\Database\SqlanywhereConnection',
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
         * @type string              $driver
         * @type ConnectionInterface $class
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
        /** @type ConnectionInterface $class */
        if (!empty($class = static::$driverConnectorMap[$driver])) {
            $class::checkRequirements();
        } else {
            throw new \Exception("Driver '$driver' is not supported by this software.");
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function make(array $config, $name = null)
    {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        /** @type ConnectionInterface $class */
        if (!empty($class = static::$driverConnectorMap[$config['driver']])) {
            $class::adaptConfig($config);
        }

        return parent::make($config, $name);
    }

    /**
     * {@inheritdoc}
     */
    public function createConnector(array $config)
    {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        if ($this->container->bound($key = "db.connector.{$config['driver']}")) {
            return $this->container->make($key);
        }

        switch ($config['driver']) {
            case 'mysql':
                return new MySqlConnector;

            case 'pgsql':
                return new PostgresConnector;

            case 'sqlite':
                return new SQLiteConnector;

            case 'sqlsrv':
                return new SqlServerConnector;

            case 'sqlanywhere':
                return new SqlAnywhereConnector;

            case 'oci8':
                return new OracleConnector();

            case 'ibm':
                return new IbmConnector;
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]");
    }

    /**
     * {@inheritdoc}
     */
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        if ($this->container->bound($key = "db.connection.{$driver}")) {
            return $this->container->make($key, [$connection, $database, $prefix, $config]);
        }

        switch ($driver) {
            case 'ibm':
                return new IbmConnection($connection, $database, $prefix, $config);

            case 'mysql':
                return new MySqlConnection($connection, $database, $prefix, $config);

            case 'oci8':
                return new OracleConnection($connection, $database, $prefix, $config);

            case 'pgsql':
                return new PostgresConnection($connection, $database, $prefix, $config);

            case 'sqlite':
                return new SQLiteConnection($connection, $database, $prefix, $config);

            case 'sqlsrv':
                return new SqlServerConnection($connection, $database, $prefix, $config);

            case 'sqlanywhere':
                return new SqlAnywhereConnection($connection, $database, $prefix, $config);
        }

        throw new InvalidArgumentException("Unsupported driver [$driver]");
    }
}
