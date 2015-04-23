<?php

namespace DreamFactory\Rave\Database\Seeds;

use DreamFactory\Rave\Models\App;
use DreamFactory\Rave\Models\RoleServiceAccess;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use DreamFactory\Rave\Models\Service;
use DreamFactory\Rave\Models\ServiceType;
use DreamFactory\Rave\Models\SystemResource;
use DreamFactory\Rave\Models\Role;

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

        //  Add native service types
        if ( true === ServiceType::seed() )
        {
            $this->command->info( 'Service types seeded!' );
        }

        //  Add the default system_resources
        if ( true === SystemResource::seed() )
        {
            $this->command->info( 'System resources seeded!' );
        }

        //  Add the default services
        if ( true === Service::seed() )
        {
            $this->command->info( 'Services seeded!' );
        }

        if( true === Role::seed())
        {
            $this->command->info( 'Default role seeded!' );
        }

        if(true === RoleServiceAccess::seed())
        {
            $this->command->info( 'Default role service access seeded!' );
        }

        if(true === App::seed())
        {
            $this->command->info( 'Default App seeded!' );
        }
    }

}
