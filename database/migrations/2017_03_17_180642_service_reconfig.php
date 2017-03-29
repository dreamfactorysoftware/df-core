<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ServiceReconfig extends Migration
{
    use \DreamFactory\Core\Components\DsnToConnectionConfig;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasColumn('service', 'config')) {
            Schema::table('service',
                function (Blueprint $table) {
                    $table->json('config')->nullable();
                }
            );

            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            $output->writeln('Scanning database to reconfigure service configurations...');

            $services = DB::table('service')->whereNotIn('type', ['system', 'swagger'])->pluck('type', 'id');
            foreach ($services as $id => $type) {
                $config = [];
                $configTable = null;
                switch ($type) {
                    case 'smtp_email':
                        if (Schema::hasTable('smtp_config')) {
                            if ($config = DB::table('smtp_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                if (!empty($params = DB::table('email_parameters_config')->where('service_id',
                                    $id)->get())
                                ) {
                                    $params = $params->toArray();
                                    foreach ($params as &$param) {
                                        $param = array_except((array)$param, ['id', 'service_id']);
                                    }
                                    $config['parameters'] = $params;
                                }
                            }
                        }
                        break;
                    case 'aws_ses':
                        if (Schema::hasTable('aws_config')) {
                            if ($config = DB::table('aws_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                if (!empty($params = DB::table('email_parameters_config')->where('service_id',
                                    $id)->get())
                                ) {
                                    $params = $params->toArray();
                                    foreach ($params as &$param) {
                                        $param = array_except((array)$param, ['id', 'service_id']);
                                    }
                                    $config['parameters'] = $params;
                                }
                            }
                        }
                        break;
                    case 'local_email':
                    case 'mailgun_email':
                    case 'mandrill_email':
                        if (Schema::hasTable('cloud_email_config')) {
                            if ($config = DB::table('cloud_email_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                if (!empty($params = DB::table('email_parameters_config')->where('service_id',
                                    $id)->get())
                                ) {
                                    $params = $params->toArray();
                                    foreach ($params as &$param) {
                                        $param = array_except((array)$param, ['id', 'service_id']);
                                    }
                                    $config['parameters'] = $params;
                                }
                            }
                        }
                        break;

                    case 'aws_s3':
                        if (Schema::hasTable('aws_config')) {
                            if ($config = DB::table('aws_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                if (!$info = DB::table('file_service_config')->where('service_id', $id)->first()) {
                                    $config = array_merge($config, (array)$info);
                                }
                            }
                        }
                        break;
                    case 'azure_blob':
                        if (Schema::hasTable('azure_config')) {
                            if ($config = DB::table('azure_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                if (!$info = DB::table('file_service_config')->where('service_id', $id)->first()) {
                                    $config = array_merge($config, (array)$info);
                                }
                            }
                        }
                        break;
                    case 'rackspace_cloud_files':
                    case 'openstack_object_storage':
                        if (Schema::hasTable('rackspace_config')) {
                            if ($config = DB::table('rackspace_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                if (!$info = DB::table('file_service_config')->where('service_id', $id)->first()) {
                                    $config = array_merge($config, (array)$info);
                                }
                            }
                        }
                        break;

                    case 'rws':
                        if (Schema::hasTable('rws_config')) {
                            if ($config = DB::table('rws_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                if (!empty($params = DB::table('rws_parameters_config')->where('service_id',
                                    $id)->get())
                                ) {
                                    $params = $params->toArray();
                                    foreach ($params as &$param) {
                                        $param = array_except((array)$param, ['id', 'service_id']);
                                    }
                                    $config['parameters'] = $params;
                                }
                                if (!empty($params = DB::table('rws_headers_config')->where('service_id',
                                    $id)->get())
                                ) {
                                    $params = $params->toArray();
                                    foreach ($params as &$param) {
                                        $param = array_except((array)$param, ['id', 'service_id']);
                                    }
                                    $config['headers'] = $params;
                                }
                                if (!$info = DB::table('service_cache_config')->where('service_id', $id)->first()) {
                                    $config = array_merge($config, (array)$info);
                                }
                            }
                        }
                        break;

                    case 'aws_redshift_db':
                    case 'ibmdb2':
                    case 'mysql':
                    case 'oracle':
                    case 'pgsql':
                    case 'sqlanywhere':
                    case 'sqlite':
                    case 'sqlsrv':
                        // special handling for connection
                        if (Schema::hasTable('sql_db_config')) {
                            if ($config = DB::table('sql_db_config')->where('service_id', $id)->first()) {
                                $config = (array)$config;
                                $connection = array_get($config, 'connection');
                                unset($config['connection']);
                                $config = array_merge($config, json_decode($connection, true));
                            }
                        }
                        break;
                    case 'local_file':
                        $configTable = 'file_service_config';
                        break;
                    case 'soap':
                        $configTable = 'soap_config';
                        break;
                    case 'nodejs':
                    case 'php':
                    case 'python':
                    case 'v8js':
                        $configTable = 'script_config';
                        break;
                    case 'aws_dynamodb':
                    case 'aws_sns':
                        $configTable = 'aws_config';
                        break;
                    case 'cache_local':
                        $configTable = 'local_cache_config';
                        break;
                    case 'cache_memcached':
                        $configTable = 'memcached_config';
                        break;
                    case 'cache_redis':
                        $configTable = 'redis_config';
                        break;
                    case 'cassandra':
                        $configTable = 'cassandra_config';
                        break;
                    case 'couchbase':
                        $configTable = 'couchbase_config';
                        break;
                    case 'couchdb':
                        $configTable = 'couchdb_config';
                        break;
                    case 'azure_table':
                        $configTable = 'azure_config';
                        break;
                    case 'azure_documentdb':
                        $configTable = 'documentdb_config';
                        break;
                    case 'mongodb':
                        $configTable = 'mongodb_config';
                        break;
                    case 'salesforce_db':
                        $configTable = 'salesforce_db_config';
                        break;
                }
                if ($configTable && Schema::hasTable($configTable)) {
                    if ($config = DB::table($configTable)->where('service_id', $id)->first()) {
                        $config = (array)$config;
                    }
                }
                if (!empty($config)) {
                    unset($config['service_id']);
                    $config = json_encode($config);
                    $output->writeln('| Service ID: ' . $id . ' New Config: ' . $config);
                    DB::table('service')->where('id', $id)->update(['config' => $config]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
