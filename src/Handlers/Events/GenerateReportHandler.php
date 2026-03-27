<?php

namespace DreamFactory\Core\Handlers\Events;

use DreamFactory\Core\Utility\Environment as EnvUtilities;
use DreamFactory\Core\Utility\Session as SessionUtilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateReportHandler
{
    public function handle()
    {
        $result = [];
        $result['platform'] = [
            'version' => \Config::get('app.version'),
            'bitnami_demo' => EnvUtilities::isDemoApplication(),
            'is_hosted' => to_bool(env('DF_MANAGED', false)),
            'is_trial' => to_bool(env('DF_IS_TRIAL', false)),
        ];

        // administrator-only information
        $dbDriver = \Config::get('database.default');
        $result['platform']['db_driver'] = $dbDriver;
        if ($dbDriver === 'sqlite') {
            $result['platform']['sqlite_storage'] = \Config::get('df.db.sqlite_storage');
        }
        $result['platform']['install_path'] = base_path() . DIRECTORY_SEPARATOR;
        $result['platform']['log_path'] = env('DF_MANAGED_LOG_PATH',
                storage_path('logs')) . DIRECTORY_SEPARATOR;
        $result['platform']['app_debug'] = env('APP_DEBUG', false);
        $result['platform']['log_mode'] = \Config::get('logging.log');
        $result['platform']['log_level'] = \Config::get('logging.log_level');
        $result['platform']['cache_driver'] = \Config::get('cache.default');

        if ($result['platform']['cache_driver'] === 'file') {
            $result['platform']['cache_path'] = \Config::get('cache.stores.file.path') . DIRECTORY_SEPARATOR;
        }

        // including information that helps users use the API or debug
        $result['server'] = php_uname();

        /*
         * Most API calls return a resource array or a single resource,
         * If an array, shall we wrap it?, With what shall we wrap it?
         */
        $result['config'] = [
            'always_wrap_resources' => \Config::get('df.always_wrap_resources'),
            'resources_wrapper' => \Config::get('df.resources_wrapper'),
            'db' => [
                /** The default number of records to return at once for database queries */
                'max_records_returned' => \Config::get('database.max_records_returned'),
                'time_format' => \Config::get('df.db.time_format'),
                'date_format' => \Config::get('df.db.date_format'),
                'datetime_format' => \Config::get('df.db.datetime_format'),
                'timestamp_format' => \Config::get('df.db.timestamp_format'),
            ],
        ];

        $connectors = DB::table('service')->whereNotNull('created_by_id')
            ->get(['name', 'type', 'created_date', 'last_modified_date']);
        $result['connectors'] = $connectors->toArray();
//            $result['php'] = EnvUtilities::getPhpInfo();
//            // Remove environment variables being kicked back to the client
//            unset($result['php']['environment']);
//            unset($result['php']['php_variables']);


        Log::channel('report')->debug('environment', $result);
    }
}
