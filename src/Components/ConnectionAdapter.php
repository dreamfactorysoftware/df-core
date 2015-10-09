<?php
namespace DreamFactory\Core\Components;

use DreamFactory\Core\Database\ConnectionFactory;
use PDO;
use Illuminate\Support\Arr;
use DreamFactory\Core\Database\Connection;
use DreamFactory\Core\Exceptions\InternalServerErrorException;

class ConnectionAdapter
{
    /**
     * @type Connection null
     */
    protected static $connection = null;

    /**
     * @param \Illuminate\Database\Connection $eloquentConnection
     *
     * @return \DreamFactory\Core\Database\Connection
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    public static function getLegacyConnection($eloquentConnection)
    {
        if (empty(static::$connection)) {
            $driver = $eloquentConnection->getDriverName();

            if (empty($driver)) {
                throw new InternalServerErrorException('No database driver supplied');
            }

            $connections = config('database.connections');

            if (empty($connections)) {
                throw new InternalServerErrorException('No connections found in database.connections config');
            }

            $configKeys = [];
            foreach ($connections as $name => $connectionConfig) {
                if ($driver === $name || $driver === $connectionConfig['driver']) {
                    $configKeys = array_keys($connectionConfig);
                }
            }

            if (empty($configKeys)) {
                throw new InternalServerErrorException('Unsupported driver - ' . $driver);
            }

            $config = [];
            foreach ($configKeys as $key) {
                $config[$key] = $eloquentConnection->getConfig($key);
            }

            switch ($driver) {
                case 'sqlite':
                    $dsn = $driver . ":" . $config['database'];
                    break;
                case 'mysql':
                    $dsn = static::getMySqlDsn($config);
                    break;
                case 'pgsql':
                    $dsn = static::getPgSqlDsn($config);
                    break;
                case 'sqlsrv':
                    $dsn = static::getSqlSrvDsn($config);
                    break;
                default:
                    throw new InternalServerErrorException('Unsupported driver - ' . $driver);
                    break;
            }

            $config['dsn'] = $dsn;
            static::$connection = ConnectionFactory::createConnection($driver, $config);
        }

        return static::$connection;
    }

    /**
     * @param array $config
     *
     * @return string
     */
    protected static function getMySqlDsn(array $config)
    {
        return isset($config['port'])
            ? "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']}"
            : "mysql:host={$config['host']};dbname={$config['database']}";
    }

    /**
     * @param array $config
     *
     * @return string
     */
    protected static function getPgSqlDsn(array $config)
    {
        // First we will create the basic DSN setup as well as the port if it is in
        // in the configuration options. This will give us the basic DSN we will
        // need to establish the PDO connections and return them back for use.

        $host = isset($config['host']) ? "host={$config['host']};" : '';

        $dsn = "pgsql:{$host}dbname={$config['database']}";

        // If a port was specified, we will add it to this Postgres DSN connections
        // format. Once we have done that we are ready to return this connection
        // string back out for usage, as this has been fully constructed here.
        if (isset($config['port'])) {
            $dsn .= ";port={$config['port']}";
        }

        if (isset($config['sslmode'])) {
            $dsn .= ";sslmode={$config['sslmode']}";
        }

        return $dsn;
    }

    /**
     * Create a DSN string from a configuration.
     *
     * @param  array $config
     *
     * @return string
     */
    protected static function getSqlSrvDsn(array $config)
    {
        // First we will create the basic DSN setup as well as the port if it is in
        // in the configuration options. This will give us the basic DSN we will
        // need to establish the PDO connections and return them back for use.
        if (in_array('dblib', static::getAvailableDrivers())) {
            return static::getDblibDsn($config);
        } else {
            return static::getSqlSrvDsn($config);
        }
    }

    /**
     * Get the DSN string for a DbLib connection.
     *
     * @param  array $config
     *
     * @return string
     */
    protected static function getDblibDsn(array $config)
    {
        $arguments = [
            'host'   => static::buildHostString($config, ':'),
            'dbname' => $config['database'],
        ];

        $arguments = array_merge(
            $arguments, Arr::only($config, ['appname', 'charset'])
        );

        return static::buildConnectString('dblib', $arguments);
    }

    /**
     * Get the DSN string for a SqlSrv connection.
     *
     * @param  array $config
     *
     * @return string
     */
    protected static function getNonDblibDsn(array $config)
    {
        $arguments = [
            'Server' => static::buildHostString($config, ','),
        ];

        if (isset($config['database'])) {
            $arguments['Database'] = $config['database'];
        }

        if (isset($config['appname'])) {
            $arguments['APP'] = $config['appname'];
        }

        return static::buildConnectString('sqlsrv', $arguments);
    }

    /**
     * Build a connection string from the given arguments.
     *
     * @param  string $driver
     * @param  array  $arguments
     *
     * @return string
     */
    protected static function buildConnectString($driver, array $arguments)
    {
        $options = array_map(function ($key) use ($arguments){
            return sprintf('%s=%s', $key, $arguments[$key]);
        }, array_keys($arguments));

        return $driver . ':' . implode(';', $options);
    }

    /**
     * Build a host string from the given configuration.
     *
     * @param  array  $config
     * @param  string $separator
     *
     * @return string
     */
    protected static function buildHostString(array $config, $separator)
    {
        if (isset($config['port'])) {
            return $config['host'] . $separator . $config['port'];
        } else {
            return $config['host'];
        }
    }

    /**
     * Get the available PDO drivers.
     *
     * @return array
     */
    protected static function getAvailableDrivers()
    {
        return PDO::getAvailableDrivers();
    }
}