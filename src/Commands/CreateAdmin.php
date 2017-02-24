<?php

namespace DreamFactory\Core\Commands;

use Illuminate\Console\Command;
use DreamFactory\Core\Models\User;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'df:create-admin
                            {--first-name= : First name}
                            {--last-name= : Last name}
                            {--email= : Email address of the user}
                            {--password= : Password for the user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates and admin user.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $fname = env('ADMIN_FIRST_NAME', $this->option('first-name'));
        $lname = env('ADMIN_LAST_NAME', $this->option('last-name'));
        $email = env('ADMIN_EMAIL', $this->option('email'));
        $password = env('ADMIN_PASSWORD', $this->option('password'));

        $data = [
            'first_name'            => $fname,
            'last_name'             => $lname,
            'email'                 => $email,
            'password'              => $password,
            'password_confirmation' => $password,
            'name'                  => $fname . ' ' . $lname
        ];

        $user = User::createFirstAdmin($data);

        if (!$user) {
            $this->error('Failed to create user.' . print_r($data['errors'], true));
        } else {
            $this->info('Successfully created user account for ' . $email);
        }
    }
}