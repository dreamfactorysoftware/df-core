<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Database\Connectors\IbmConnector;
use DreamFactory\Core\Database\Connectors\OracleConnector;
use DreamFactory\Core\Database\Connectors\SqlAnywhereConnector;
use DreamFactory\Core\Database\Connectors\SQLiteConnector;
use DreamFactory\Core\Database\IbmConnection;
use DreamFactory\Core\Database\OracleConnection;
use DreamFactory\Core\Database\SqlAnywhereConnection;
use DreamFactory\Core\Handlers\Events\ServiceEventHandler;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\ServiceProvider;

class DfServiceProvider extends ServiceProvider
{
    public function register()
    {
        \Event::subscribe(new ServiceEventHandler());

        // Add our database drivers.
        $this->app->resolving('db', function ($db) {
            $db->extend('sqlite', function ($config) {
                $connector  = new SQLiteConnector();
                $connection = $connector->connect($config);
                return new SQLiteConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('sqlanywhere', function ($config) {
                $connector  = new SqlAnywhereConnector();
                $connection = $connector->connect($config);
                return new SqlAnywhereConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('oracle', function ($config) {
                $connector  = new OracleConnector();
                $connection = $connector->connect($config);
                return new OracleConnection($connection, $config["database"], $config["prefix"], $config);
            });
            $db->extend('ibm', function ($config) {
                $connector  = new IbmConnector();
                $connection = $connector->connect($config);
                return new IbmConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });
    }
}
