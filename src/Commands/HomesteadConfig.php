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
    protected $signature = 'df:homestead-config {--dev : Setup workbench for all packages and configure PSR-4 autoloader for them}';

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
            $script = ($this->option('dev'))? 'server/config/homestead/after-dev.sh' : 'server/config/homestead/after.sh';


            $this->info('----------------------------------------------------------------------------');
            $this->info('Configuring Homestead with following settings: ');
            $this->info('IP: 192.168.10.10');
            $this->info('Memory: ' . $memory);
            $this->info('CPUs: ' . $cpus);
            $this->info('Box Version: ' . $version);
            $this->info('Script: ' . $script);
            $this->info('----------------------------------------------------------------------------');
            $this->warn('Edit Homestead.yaml file if you like to change any of the above settings.');
            exec("php vendor/bin/homestead make", $out);
            $output = implode('\n', $out);
            $this->info($output);
            $this->info('You can now run "vagrant up" to provision your homestead vagrant box.');
            $this->info('----------------------------------------------------------------------------');

            if (file_exists('Homestead.yaml')) {
                file_put_contents('Homestead.yaml',
                    str_replace("memory: 2048", "memory: $memory", file_get_contents('Homestead.yaml')));
                file_put_contents('Homestead.yaml',
                    str_replace("cpus: 1", "cpus: $cpus", file_get_contents('Homestead.yaml')));
                if (strpos(file_get_contents('Homestead.yaml'), 'version:') === false) {
                    file_put_contents('Homestead.yaml',
                        str_replace("provider: virtualbox", "provider: virtualbox\nversion: $version",
                            file_get_contents('Homestead.yaml'))
                    );
                }
                if (strpos(file_get_contents('Homestead.yaml'), 'script:') === false) {
                    file_put_contents('Homestead.yaml',
                        str_replace("provider: virtualbox", "provider: virtualbox\nscript: $script",
                            file_get_contents('Homestead.yaml'))
                    );
                }
//                if(strpos(file_get_contents('Homestead.yaml'), 'type: "nfs"') === false){
//                    file_put_contents('Homestead.yaml',
//                        str_replace("to: /home/vagrant/code\n", "to: /home/vagrant/code\n        type: \"nfs\"\n",
//                            file_get_contents('Homestead.yaml'))
//                    );
//                }
                if(strpos(file_get_contents('Homestead.yaml'), 'php: "7.1"') === false){
                    file_put_contents('Homestead.yaml',
                        str_replace("to: /home/vagrant/code/public\n", "to: /home/vagrant/code/public\n        php: \"7.1\"\n",
                            file_get_contents('Homestead.yaml'))
                    );
                }
            }

            // Remove after.sh and aliases files create by Homestead configurator
            // as we supply our own in server/config/homestead/ diretory.
            @unlink('after.sh');
            @unlink('aliases');
        }
    }
}
