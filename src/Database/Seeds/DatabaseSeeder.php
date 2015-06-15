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

        $this->call('DreamFactory\\Core\\Database\\Seeds\\ServiceTypeSeeder');
        $this->call('DreamFactory\\Core\\Database\\Seeds\\SystemResourceSeeder');
        $this->call('DreamFactory\\Core\\Database\\Seeds\\ScriptTypeSeeder');
        $this->call('DreamFactory\\Core\\Database\\Seeds\\ServiceSeeder');
        $this->call('DreamFactory\\Core\\Database\\Seeds\\AdminSeeder');
        $this->call('DreamFactory\\Core\\Database\\Seeds\\DbTableExtrasSeeder');
        $this->call('DreamFactory\\Core\\Database\\Seeds\\RoleAndAppSeeder');
    }
}
