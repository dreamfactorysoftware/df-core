<?php

namespace DreamFactory\Core\Database\Connectors;

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
use Yajra\Oci8\Connectors\OracleConnector;

class ConnectionFactory extends \Illuminate\Database\Connectors\ConnectionFactory
{
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

        return true;
    }

    public static function adaptConnection(\Illuminate\Database\Connection $connection)
    {
        // clients now use indirect association to drivers, dblib can be sqlsrv or sqlanywhere
        $driver = $connection->getDriverName();
//        if (isset(static::$driverConnectorMap[$driver])) {
//            $class = static::$driverConnectorMap[$driver];
//
//            /** @type Connection $adaptation */
//            $adaptation = new $class(['driver' => $connection->getDriverName()]);
//            $adaptation->setConnection($connection);
//
//            return $adaptation;
//        } else {
//            throw new \Exception("ConnectionFactory does not support creating connections for '$driver' driver.");
//        }
    }

    /**
     * Create a connector instance based on the configuration.
     *
     * @param  array  $config
     * @return \Illuminate\Database\Connectors\ConnectorInterface
     *
     * @throws \InvalidArgumentException
     */
    public function createConnector(array $config)
    {
        if (! isset($config['driver'])) {
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

            case 'oci':
                return new OracleConnector;

            case 'ibm':
                return new IbmConnector;
        }

        throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]");
    }

    /**
     * Create a new connection instance.
     *
     * @param  string   $driver
     * @param  \PDO|\Closure     $connection
     * @param  string   $database
     * @param  string   $prefix
     * @param  array    $config
     * @return \Illuminate\Database\Connection
     *
     * @throws \InvalidArgumentException
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

            case 'oci':
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
