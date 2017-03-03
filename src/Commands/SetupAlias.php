<?php

namespace DreamFactory\Core\Commands;

use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\FileUtilities;
use Illuminate\Console\Command;

class SetupAlias extends Setup
{
    /**
     * The alias for the old dreamfactory:setup.
     *
     * @var string
     */
    protected $signature = 'dreamfactory:setup
                            {--force : Force run migration and seeder.}
                            {--no-app-key : Skip generating APP_KEY. }
                            {--db_host= : Database host.}
                            {--db_connection= : System database driver. [sqlite, mysql, pgsql, sqlsrv].}
                            {--db_database= : Database name.}
                            {--db_username= : Database username.}
                            {--db_password= : Database password.}
                            {--db_port= : Database port.}
                            {--df_install=GitHub : Installation source/environment.}
                            {--cache_driver= : System cache driver. [file, redis, memcached]}
                            {--redis_host= : Cache driver redis host}
                            {--redis_port= : Cache driver redis port}
                            {--redis_database= : Cache driver redis database}
                            {--redis_password= : Cache driver redis password}
                            {--memcached_host= : Cache driver memcached host}
                            {--memcached_port= : Cache driver memcached port}
                            {--memcached_weight= : Cache driver memcached weight}
                            {--admin_first_name= : Admin user first name}
                            {--admin_last_name= : Admin user last name}
                            {--admin_email= : Admin user email}
                            {--admin_password= : Admin user password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup DreamFactory 2.0 instance (Deprecated - old namespace).';
}
