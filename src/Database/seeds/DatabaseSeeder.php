<?php

namespace DreamFactory\Rave\Database\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use DreamFactory\Rave\Models\ServiceType;
use DreamFactory\Rave\Models\SystemResource;

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

        //Add native service types
        if(true===ServiceType::seed())
        {
            $this->command->info( 'Service types seeded!' );
        }

        // Add the default system_resources
        if(true===SystemResource::seed())
        {
            $this->command->info( 'System resources seeded!' );
        }
    }

}
