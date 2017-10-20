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
            $set = strtolower($this->ask('Would you like to configure Homestead vagrant box now? (yes/no)'));
            if($set === 'yes' || $set === 'y') {
                $ip = $this->ask('Private IP', '192.168.10.10');
                $memory = $this->ask('Memory', 4096);
                $cpus = $this->ask('CPUs', 2);
                $version = "3.1.0";
                $this->info('Configuring Homestead using box version ' . $version);
                exec("php vendor/bin/homestead make", $out);
                $output = implode('\n', $out);
                $this->info($output);

                if (file_exists('Homestead.yaml')) {
                    file_put_contents('Homestead.yaml',
                        str_replace("ip: 192.168.10.10", "ip: $ip", file_get_contents('Homestead.yaml')));
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
            } else {
                $this->info('Homestead was not configured.');
            }
        }
    }
}
