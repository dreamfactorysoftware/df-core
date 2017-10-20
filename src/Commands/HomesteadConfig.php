<?php

namespace DreamFactory\Core\Commands;

use Illuminate\Console\Command;

class HomesteadConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'df:homestead-config';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (file_exists('vendor/bin/homestead')) {
            $memory = 4096;
            $cpus = 2;
            $version = "3.1.0";

            $this->info('Configuring Homestead with following settings: ');
            $this->info('IP: 192.168.10.10');
            $this->info('Memory: ' . $memory);
            $this->info('CPUs: ' . $cpus);
            $this->info('Box Version: ' . $version);

            exec("php vendor/bin/homestead make", $out);
            $output = implode('\n', $out);
            $this->info($output);

            if (file_exists('Homestead.yaml')) {
                file_put_contents('Homestead.yaml',
                    str_replace("memory: 2048", "memory: $memory", file_get_contents('Homestead.yaml')));
                file_put_contents('Homestead.yaml',
                    str_replace("cpus: 1", "cpus: $cpus", file_get_contents('Homestead.yaml')));
                if (strpos(file_get_contents('Homestead.yaml'), 'version:') === false) {
                    file_put_contents('Homestead.yaml',
                        str_replace("provider: virtualbox", "provider: virtualbox\nversion: $version",
                            file_get_contents('Homestead.yaml')));
                }
            }
        }
    }
}
