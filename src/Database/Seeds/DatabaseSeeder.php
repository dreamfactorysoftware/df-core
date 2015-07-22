<?php
namespace DreamFactory\Core\Database\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        $this->call(ServiceTypeSeeder::class);
        $this->call(SystemResourceSeeder::class);
        $this->call(ScriptTypeSeeder::class);
        $this->call(ServiceSeeder::class);
        //$this->call(AdminSeeder::class);
        $this->call(DbTableExtrasSeeder::class);
        $this->call(AppSeeder::class);
    }
}
