<?php

namespace DreamFactory\Core\Commands;

use DreamFactory\Core\Models\User;
use Illuminate\Console\Command;

class Setup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'df:setup
                            {--force : Force run migration and seeder}
                            {--no-app-key : Skip generating APP_KEY}
                            {--admin_first_name= : Admin user first name}
                            {--admin_last_name= : Admin user last name}
                            {--admin_email= : Admin user email}
                            {--admin_password= : Admin user password}
                            {--admin_phone= : Admin phone number}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup DreamFactory 2.0 instance.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            if ($result = User::adminExists()) {
                $this->error('Your instance is already setup.');

                return;
            }
        } catch (\Exception $e) {
            // models may not be setup, keep going
        }

        try {
            $force = $this->option('force');

            if (!file_exists('phpunit.xml')) {
                copy('phpunit.xml-dist', 'phpunit.xml');
                $this->info('Created phpunit.xml with default configuration.');
            }

            if (!file_exists('.env')) {
                copy('.env-dist', '.env');
                $this->info('Created .env file with default configuration.');
            }

            if (empty(env('APP_KEY'))) {
                if (false === $this->option('no-app-key')) {
                    $this->call('key:generate');
                } else {
                    $this->info('Skipping APP_KEY generate.');
                }
            }

            $this->info('**********************************************************************************************************************');
            $this->info('* Welcome to DreamFactory Setup.');
            $this->info('**********************************************************************************************************************');

            $this->info('Running Migrations...');
            $this->call('migrate', ['--force' => $force]);
            $this->info('Migration completed successfully.');
            $this->info('**********************************************************************************************************************');

            $this->info('**********************************************************************************************************************');
            $this->info('Running Seeder...');
            $this->call('db:seed', ['--force' => $force]);
            $this->info('All tables were seeded successfully.');
            $this->info('**********************************************************************************************************************');

            $this->info('**********************************************************************************************************************');
            $this->info('Creating the first admin user...');
            $user = false;
            while (!$user) {
                $firstName = $this->option('admin_first_name');
                $lastName = $this->option('admin_last_name');
                $email = $this->option('admin_email');
                $password = $this->option('admin_password');
                $phone = $this->option('admin_phone');
                $prompt = true;
                if (!empty($email) && !empty($password)) {
                    $prompt = false;
                }

                if (empty($firstName)) {
                    $firstName = ($prompt) ? $this->ask('Enter your first name') : 'FirstName';
                }
                if (empty($lastName)) {
                    $lastName = ($prompt) ? $this->ask('Enter your last name') : 'LastName';
                }
                if (empty($email)) {
                    $email = $this->ask('Enter your email address');
                }
                if (empty($phone)) {
                    $phone = $this->ask('Enter your phone number');
                }
                if (empty($password)) {
                    $password = $this->secret('Choose a password');
                }

                $passwordConfirm = ($prompt) ? $this->secret('Re-enter password') : $password;
                $displayName = empty($displayName) ? $firstName . ' ' . $lastName : $displayName;

                $gdpr = $this->choice(
                  'I consent to receiving occasional marketing messages from DreamFactory',
                  ['No', 'Yes'],
                  1,
                  $maxAttempts = null,
                  $allowMultipleSelections = false
                ) === 'Yes' ? 'On' : false;

                $data = [
                    'first_name'            => $firstName,
                    'last_name'             => $lastName,
                    'email'                 => $email,
                    'password'              => $password,
                    'password_confirmation' => $passwordConfirm,
                    'name'                  => $displayName,
                    'phone'                 => $phone,
                    'gdpr'                  => $gdpr,
                ];

                $user = User::createFirstAdmin($data);

                if (!$user) {
                    $this->error('Failed to create user.' . print_r($data['errors'], true));
                    $this->info('Please try again...');
                }
            }
            $this->info('Successfully created first admin user.');
            $this->info('**********************************************************************************************************************');

            $this->warn('*************************************************** WARNING! *********************************************************');
            $this->warn('* Please make sure following directories and all directories under them are readable and writable by your web server ');
            $this->warn('*   -> storage/');
            $this->warn('*   -> bootstrap/cache/');
            $this->warn('* Example:');
            $this->warn('*      > sudo chown -R {www user}:{your user group} storage/ bootstrap/cache/ ');
            $this->warn('*      > sudo chmod -R 2775 storage/ bootstrap/cache/ ');
            $this->warn('**********************************************************************************************************************');
            $this->info('*********************************************** Setup Successful! ****************************************************');
            $this->info('* Setup is complete! Your instance is ready. Please launch your instance using a browser.');
            $this->info('* You can run "php artisan serve" to try out your instance without setting up a web server.');
            $this->info('**********************************************************************************************************************');
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Used to determine interactive mode on/off
     *
     * @return bool
     */
    protected function doInteractive()
    {
        $interactive = true;
        $options = $this->option();

        foreach ($options as $key => $value) {
            if (substr($key, 0, 6) === 'admin_' && !empty($value)) {
                $interactive = false;
            }
        }

        return $interactive;
    }
}
