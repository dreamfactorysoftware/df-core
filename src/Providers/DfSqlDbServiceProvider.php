<?php
namespace DreamFactory\Core\Providers;

use DreamFactory\Core\Database\Connectors\SQLiteConnector;
use DreamFactory\Core\Database\DbSchemaExtensions;
use DreamFactory\Core\Database\Schema\MySqlSchema;
use DreamFactory\Core\Database\Schema\PostgresSchema;
use DreamFactory\Core\Database\Schema\SqliteSchema;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\ServiceProvider;

class DfSqlDbServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Add our database drivers.
        $this->app->resolving('db', function (DatabaseManager $db){
            $db->extend('sqlite', function ($config){
                $connector = new SQLiteConnector();
                $connection = $connector->connect($config);

                return new SQLiteConnection($connection, $config["database"], $config["prefix"], $config);
            });
        });

        // The database schema extension manager is used to resolve various database schema extensions.
        // It also implements the resolver interface which may be used by other components adding schema extensions.
        $this->app->singleton('db.schema', function ($app){
            return new DbSchemaExtensions($app);
        });

        // Add our database extensions.
        $this->app->resolving('db.schema', function (DbSchemaExtensions $db){
            $db->extend('sqlite', function ($connection){
                return new SqliteSchema($connection);
            });
            $db->extend('mysql', function ($connection){
                return new MySqlSchema($connection);
            });
            $db->extend('pgsql', function ($connection){
                return new PostgresSchema($connection);
            });
        });
    }
}
