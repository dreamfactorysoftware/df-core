<?php

use Illuminate\Database\Migrations\Migration;

class UpgradeServiceTypes extends Migration
{
    use \DreamFactory\Core\Components\DsnToConnectionConfig;

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('service_type')) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            $output->writeln('Scanning database for old sql_db service type...');
            $ids = DB::table('service')->where('type', 'sql_db')->pluck('id');
            if (!empty($ids)) {
                $configs = DB::table('sql_db_config')->whereIn('service_id', $ids)->get();
                $output->writeln('|--------------------------------------------------------------------');
                foreach ($configs as $entry) {
                    /** @type array $entry */
                    $entry = (array)$entry;
                    $newType = '';
                    $config = static::adaptConfig($entry, $newType);
                    $config = json_encode($config);
                    $id = array_get($entry, 'service_id');
                    $output->writeln('| Service ID: ' . $id . ' New Config: ' . $config);
                    DB::table('service')->where('id', $id)->update(['type' => $newType]);
                    DB::table('sql_db_config')->where('service_id', $id)->update(['connection' => $config]);
                }
                $output->writeln('|--------------------------------------------------------------------');
            }

            $output->writeln('Scanning database for old script service type...');
            $ids = DB::table('service')->where('type', 'script')->pluck('id');
            if (!empty($ids)) {
                $configs = DB::table('script_config')->whereIn('service_id', $ids)->pluck('type', 'service_id');
                $output->writeln('|--------------------------------------------------------------------');
                foreach ($configs as $id => $driver) {
                    $newType = $driver;
                    $output->writeln('| ID: ' . $id . ' New Type: ' . $newType);
                    DB::table('service')->where('id', $id)->update(['type' => $newType]);
                }
                $output->writeln('|--------------------------------------------------------------------');
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
