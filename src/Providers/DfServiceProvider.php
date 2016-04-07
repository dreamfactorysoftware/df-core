<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Database\Connectors\IbmConnector;
use DreamFactory\Core\Database\Connectors\OracleConnector;
use DreamFactory\Core\Database\Connectors\SqlAnywhereConnector;
use DreamFactory\Core\Database\Connectors\SqlServerConnector;
use DreamFactory\Core\Database\IbmConnection;
use DreamFactory\Core\Database\MySqlConnection;
use DreamFactory\Core\Database\OracleConnection;
use DreamFactory\Core\Database\PostgresConnection;
use DreamFactory\Core\Database\SqlAnywhereConnection;
use DreamFactory\Core\Database\SQLiteConnection;
use DreamFactory\Core\Database\SqlServerConnection;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use Illuminate\Database\Connectors\MySqlConnector;
use Illuminate\Database\Connectors\PostgresConnector;
use Illuminate\Database\Connectors\SQLiteConnector;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
{
    public function register()
    {
        \Event::subscribe(new ServiceEventHandler());

        // Add our database drivers.
        $this->app->resolving('db', function ($db) {
            $db->extend('sqlite', function ($config) {
                SQLiteConnection::adaptConfig($config);
                $connector  = new SQLiteConnector();
                $connection = $connector->connect($config);
                return new SQLiteConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('mysql', function ($config) {
                MySqlConnection::adaptConfig($config);
                $connector  = new MySqlConnector();
                $connection = $connector->connect($config);
                return new MySqlConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('pgsql', function ($config) {
                PostgresConnection::adaptConfig($config);
                $connector  = new PostgresConnector();
                $connection = $connector->connect($config);
                return new PostgresConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('sqlsrv', function ($config) {
                SqlServerConnection::adaptConfig($config);
                $connector  = new SqlServerConnector();
                $connection = $connector->connect($config);
                return new SqlServerConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('sqlanywhere', function ($config) {
                SqlAnywhereConnection::adaptConfig($config);
                $connector  = new SqlAnywhereConnector();
                $connection = $connector->connect($config);
                return new SqlAnywhereConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('oracle', function ($config) {
                OracleConnection::adaptConfig($config);
                $connector  = new OracleConnector();
                $connection = $connector->connect($config);
                return new OracleConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('ibm', function ($config) {
                IbmConnection::adaptConfig($config);
                $connector  = new IbmConnector();
                $connection = $connector->connect($config);
                return new IbmConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });
    }
}
