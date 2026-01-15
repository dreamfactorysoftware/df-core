<?php

namespace DreamFactory\Core\Commands;

use DreamFactory\Core\Utility\FileUtilities;
use Illuminate\Console\Command;

class Env extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'df:env
                            {--db_connection= : System database driver. [sqlite, mysql, pgsql, sqlsrv]}
                            {--db_host= : Database host}
                            {--db_port= : Database port}
                            {--db_database= : Database name}
                            {--db_username= : Database username}
                            {--db_password= : Database password}
                            {--cache_store= : System cache store [file, redis, memcached]}
                            {--redis_host= : Cache store redis host}
                            {--redis_port= : Cache store redis port}
                            {--redis_database= : Cache store redis database}
                            {--redis_password= : Cache store redis password}
                            {--memcached_host= : Cache store memcached host}
                            {--memcached_port= : Cache store memcached port}
                            {--memcached_weight= : Cache store memcached weight}
                            {--df_install=GitHub : Installation source/environment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set Environment for DreamFactory';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('**********************************************************************************************************************');
        $this->info('* Configuring DreamFactory... ');
        $this->info('**********************************************************************************************************************');

        if (!file_exists('.env')) {
            copy('.env-dist', '.env');
            $this->info('Created .env file with default configuration.');
        }

        if (!file_exists('phpunit.xml')) {
            copy('phpunit.xml-dist', 'phpunit.xml');
            $this->info('Created phpunit.xml with default configuration.');
        }

        if ($this->doInteractive()) {
            $db = $this->choice('What type of database will house the DreamFactory system information?',
                ['sqlite', 'mysql', 'pgsql', 'sqlsrv'], 0);

            if ('sqlite' === $db) {
                $database = $this->ask('Enter the database name (this database must exist)');
                $config = [
                    'DB_CONNECTION' => $db,
                    'DB_DATABASE'   => $database,
                    'DF_INSTALL'    => $this->option('df_install')
                ];
            } else {
                $driver = $db;
                $host = $this->ask('Enter the ' . $db . ' Host');
                $port = $this->ask('Enter the database port', config('database.connections.' . $db . '.port'));
                $database = $this->ask('Enter the database name');
                $username = $this->ask('Enter the database username');

                $password = '';
                $passwordMatch = false;
                while (!$passwordMatch) {
                    $password = $this->secret('Enter the database password');
                    $password2 = $this->secret('Re-enter the database password');

                    if ($password === $password2) {
                        $passwordMatch = true;
                    } else {
                        $this->error('The passwords did not match. Please try again.');
                    }
                }

                $config = [
                    'DB_CONNECTION' => $driver,
                    'DB_HOST'       => $host,
                    'DB_DATABASE'   => $database,
                    'DB_USERNAME'   => $username,
                    'DB_PASSWORD'   => $password,
                    'DB_PORT'       => $port,
                    'DF_INSTALL'    => $this->option('df_install')
                ];
            }
        } else {
            $driver = $this->option('db_connection');
            if (!in_array($driver, ['sqlite', 'mysql', 'pgsql', 'sqlsrv'])) {
                $this->warn('DB DRIVER ' . $driver . ' is not supported. Using default driver sqlite.');
                $driver = 'sqlite';
            }

            $config = [];
            if ('sqlite' === $driver) {
                static::setIfValid($config, 'DB_CONNECTION', $this->option('db_connection'));
                static::setIfValid($config, 'DB_DATABASE', $this->option('db_database'));
            } else {
                static::setIfValid($config, 'DF_INSTALL', $this->option('df_install'));
                static::setIfValid($config, 'DB_HOST', $this->option('db_host'));
                static::setIfValid($config, 'DB_CONNECTION', $this->option('db_connection'));
                static::setIfValid($config, 'DB_DATABASE', $this->option('db_database'));
                static::setIfValid($config, 'DB_USERNAME', $this->option('db_username'));
                static::setIfValid($config, 'DB_PASSWORD', $this->option('db_password'));
                static::setIfValid($config, 'DB_PORT', $this->option('db_port'));
            }
        }

        $cacheStore = $this->option('cache_store');
        if (!in_array($cacheStore, ['file', 'redis', 'memcached'])) {
            $this->warn('CACHE STORE ' . $cacheStore . ' is not supported. Using default store file.');
            $cacheStore = 'file';
        }

        static::setIfValid($config, 'CACHE_STORE', $cacheStore);

        if ('redis' === strtolower($cacheStore)) {
            static::setIfValid($config, 'REDIS_HOST', $this->option('redis_host'));
            static::setIfValid($config, 'REDIS_PORT', $this->option('redis_port'));
            static::setIfValid($config, 'REDIS_DATABASE', $this->option('redis_database'));
            static::setIfValid($config, 'REDIS_PASSWORD', $this->option('redis_password'));
        } elseif ('memcached' === strtolower($cacheStore)) {
            static::setIfValid($config, 'MEMCACHED_HOST', $this->option('memcached_host'));
            static::setIfValid($config, 'MEMCACHED_PORT', $this->option('memcached_port'));
            static::setIfValid($config, 'MEMCACHED_WEIGHT', $this->option('memcached_weight'));
        }
        FileUtilities::updateEnvSetting($config);
        $this->info('Configuration complete!');
        $this->warn('******************************************* WARNING! *****************************************************');
        $this->warn('*');
        $this->warn('* Please take a moment to review the .env file. You can make any changes as necessary there. ');
        $this->warn('*');
        $this->warn('* Please run "php artisan df:setup" to complete the setup process.');
        $this->warn('*');
        $this->warn('**********************************************************************************************************');
    }

    /**
     * Used to determine interactive mode on/off
     *
     * @return bool
     */
    protected function doInteractive()
    {
        $interactive = true;
        $options = $this->option();

        foreach ($options as $key => $value) {
            if (substr($key, 0, 3) === 'db_' && !empty($value)) {
                $interactive = false;
            }
        }

        return $interactive;
    }

    /**
     * @param $array
     * @param $key
     * @param $value
     */
    protected static function setIfValid(& $array, $key, $value)
    {
        if (!empty($value)) {
            $array[$key] = $value;
        }
    }
}
