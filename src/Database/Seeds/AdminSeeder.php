<?php
namespace DreamFactory\Core\Database\Seeds;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use DreamFactory\Core\Models\User;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Model::unguard();

        // Add default admin user
        if (!User::exists()) {
            User::create(
                [
                    'name'         => 'DF Admin',
                    'email'        => 'dfadmin@' . gethostname() . '.com',
                    'password'     => 'Dream123!',
                    'is_sys_admin' => 1,
                    'is_active'    => 1
                ]
            );

            $this->command->info('Admin user seeded!');
        }
    }
}
