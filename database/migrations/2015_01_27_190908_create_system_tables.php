<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Symfony\Component\Console\Output\ConsoleOutput;

class CreateSystemTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $driver = Schema::getConnection()->getDriverName();
        $sqlsrv = (('sqlsrv' === $driver) || ('dblib' === $driver));
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.
        $userOnDelete = ($sqlsrv ? 'no action' : 'set null');

        $output = new ConsoleOutput();
        $output->writeln("Migration driver used: $driver");

        // User table
        Schema::create(
            'user',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name');
                $t->string('first_name')->nullable();
                $t->string('last_name')->nullable();
                $t->dateTime('last_login_date')->nullable();
                $t->string('email')->unique();
                $t->longText('password')->nullable();
                $t->boolean('is_sys_admin')->default(0);
                $t->boolean('is_active')->default(1);
                $t->string('phone', 32)->nullable();
                $t->string('security_question')->nullable();
                $t->longText('security_answer')->nullable();
                $t->string('confirm_code')->nullable();
                $t->integer('default_app_id')->nullable();
                $t->rememberToken();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // User Lookup Keys
        Schema::create(
            'user_lookup',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->integer('user_id')->unsigned();
                $t->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
                $t->string('name')->index();
                $t->text('value')->nullable();
                $t->boolean('private')->default(0);
                $t->text('description')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Password reset table
        Schema::create(
            'password_resets',
            function (Blueprint $t){
                $t->string('email')->index();
                $t->string('token')->index();
                $t->timestamp('created_date');
            }
        );

        // Services
        Schema::create(
            'service',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name', 40)->unique();
                $t->string('label', 80);
                $t->string('description')->nullable();
                $t->boolean('is_active')->default(0);
                $t->string('type', 40);
                $t->boolean('mutable')->default(1);
                $t->boolean('deletable')->default(1);
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Service API Docs
        Schema::create(
            'service_doc',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->integer('format')->unsigned()->default(0);
                $t->mediumText('content')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
            }
        );

        // Script Service Config
        Schema::create(
            'script_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->text('content')->nullable();
                $t->text('config')->nullable();
            }
        );

        // Event Subscriber
        Schema::create(
            'event_subscriber',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name', 80)->unique();
                $t->string('type');
                $t->text('config')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Event Scripts
        Schema::create(
            'event_script',
            function (Blueprint $t) use ($userOnDelete){
                $t->string('name', 80)->primary();
                $t->string('type', 40);
                $t->boolean('is_active')->default(0);
                $t->boolean('affects_process')->default(0);
                $t->text('content')->nullable();
                $t->text('config')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Roles
        Schema::create(
            'role',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name', 64)->unique();
                $t->string('description')->nullable();
                $t->boolean('is_active')->default(0);
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Roles to Services Allowed Accesses
        Schema::create(
            'role_service_access',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->integer('role_id')->unsigned();
                $t->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
                $t->integer('service_id')->unsigned()->nullable();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('component')->nullable();
                $t->integer('verb_mask')->unsigned()->default(0);
                $t->integer('requestor_mask')->unsigned()->default(0);
                $t->text('filters')->nullable();
                $t->string('filter_op', 32)->default('and');
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Role Lookup Keys
        Schema::create(
            'role_lookup',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->integer('role_id')->unsigned();
                $t->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
                $t->string('name')->index();
                $t->text('value')->nullable();
                $t->boolean('private')->default(0);
                $t->text('description')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // System Lookups
        Schema::create(
            'system_lookup',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name')->unique();
                $t->text('value')->nullable();
                $t->boolean('private')->default(0);
                $t->text('description')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Email Templates
        Schema::create(
            'email_template',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name', 64)->unique();
                $t->string('description')->nullable();
                $t->text('to')->nullable();
                $t->text('cc')->nullable();
                $t->text('bcc')->nullable();
                $t->string('subject', 80)->nullable();
                $t->text('body_text')->nullable();
                $t->text('body_html')->nullable();
                $t->string('from_name', 80)->nullable();
                $t->string('from_email')->nullable();
                $t->string('reply_to_name', 80)->nullable();
                $t->string('reply_to_email')->nullable();
                $t->text('defaults')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Database Table Extras
        Schema::create(
            'db_table_extras',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('table');
                $t->string('label')->nullable();
                $t->string('plural')->nullable();
                $t->string('name_field', 128)->nullable();
                $t->string('model')->nullable();
                $t->text('description')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Database Field Extras
        Schema::create(
            'db_field_extras',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('table');
                $t->string('field');
                $t->string('label')->nullable();
                $t->string('extra_type')->nullable();
                $t->text('description')->nullable();
                $t->text('picklist')->nullable();
                $t->text('validation')->nullable();
                $t->text('client_info')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // System Configuration
        Schema::create(
            'system_config',
            function (Blueprint $t) use ($userOnDelete){
                $t->string('db_version', 32)->primary();
                $t->boolean('login_with_user_name')->default(0);
                $t->integer('default_app_id')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        //Cors config table
        Schema::create(
            'cors_config',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('path')->unique();
                $t->string('origin');
                $t->longText('header');
                $t->integer('method')->default(0);
                $t->integer('max_age')->default(3600);
                $t->boolean('enabled')->default(true);
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        //Email service config table
        Schema::create(
            'smtp_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('host');
                $t->string('port')->default('587');
                $t->string('encryption')->default('tls');
                $t->longText('username'); //encrypted
                $t->longText('password'); //encrypted
            }
        );

        //Email service config table
        Schema::create(
            'cloud_email_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('domain')->nullable();
                $t->longText('key'); //encrypted
            }
        );

        //Email service parameters config table
        Schema::create(
            'email_parameters_config',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->string('name');
                $t->mediumText('value')->nullable();
                $t->boolean('active')->default(1);
            }
        );

        // Applications
        Schema::create(
            'app',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name', 64)->unique();
                $t->string('api_key')->nullable();
                $t->string('description')->nullable();
                $t->boolean('is_active')->default(0);
                $t->integer('type')->unsigned()->default(0);
                $t->text('path')->nullable();
                $t->text('url')->nullable();
                $t->integer('storage_service_id')->unsigned()->nullable();
                $t->foreign('storage_service_id')->references('id')->on('service')->onDelete('set null');
                $t->string('storage_container', 255)->nullable();
                $t->boolean('requires_fullscreen')->default(0);
                $t->boolean('allow_fullscreen_toggle')->default(1);
                $t->string('toggle_location', 64)->default('top');
                $t->integer('role_id')->unsigned()->nullable();
                $t->foreign('role_id')->references('id')->on('role')->onDelete('set null');
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // App Lookup Keys
        Schema::create(
            'app_lookup',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->integer('app_id')->unsigned();
                $t->foreign('app_id')->references('id')->on('app')->onDelete('cascade');
                $t->string('name')->index();
                $t->text('value')->nullable();
                $t->boolean('private')->default(0);
                $t->text('description')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Application Groups - visual aid for Launchpad only
        Schema::create(
            'app_group',
            function (Blueprint $t) use ($userOnDelete){
                $t->increments('id');
                $t->string('name', 64)->unique();
                $t->string('description')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );

        // Apps to App Groups Relationships - visual aid for Launchpad only
        Schema::create(
            'app_to_app_group',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('app_id')->unsigned();
                $t->foreign('app_id')->references('id')->on('app')->onDelete('cascade');
                $t->integer('group_id')->unsigned();
                $t->foreign('group_id')->references('id')->on('app_group')->onDelete('cascade');
            }
        );

        // App relationship for user
        Schema::create(
            'user_to_app_to_role',
            function (Blueprint $t){
                $t->increments('id');
                $t->integer('user_id')->unsigned();
                $t->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
                $t->integer('app_id')->unsigned();
                $t->foreign('app_id')->references('id')->on('app')->onDelete('cascade');
                $t->integer('role_id')->unsigned();
                $t->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
            }
        );

        // JSON Web Token to system resources map
        Schema::create(
            'token_map',
            function (Blueprint $t){
                $t->integer('user_id')->unsigned();
                $t->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
                $t->text('token');
                $t->integer('iat')->unsigned();
                $t->integer('exp')->unsigned();
            }
        );

        Schema::create(
            'file_service_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->text('public_path')->nullable();
                $t->text('container')->nullable();
            }
        );

        Schema::create(
            'service_cache_config',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->boolean('cache_enabled')->default(0);
                $t->integer('cache_ttl')->default(0);
            }
        );

        // create system customizations
        Schema::create(
            'system_custom',
            function (Blueprint $t) use ($userOnDelete){
                $t->string('name')->primary();
                $t->longText('value')->nullable();
                $t->timestamp('created_date');
                $t->timestamp('last_modified_date');
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($userOnDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($userOnDelete);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop created tables in reverse order

        // system customizations
        Schema::dropIfExists('system_custom');
        // File storage, public path designation
        Schema::dropIfExists('file_service_config');
        // Cache-able service configuration
        Schema::dropIfExists('service_cache_config');
        // JSON Web Token to system resources map
        Schema::dropIfExists('token_map');
        // App relationship for user
        Schema::dropIfExists('user_to_app_to_role');
        // Service Docs
        Schema::dropIfExists('service_doc');
        // Script Service Configs
        Schema::dropIfExists('script_config');
        // Event Scripts
        Schema::dropIfExists('event_script');
        // Event Subscribers
        Schema::dropIfExists('event_subscriber');
        // System Configuration
        Schema::dropIfExists('system_config');
        // Role Lookup Keys
        Schema::dropIfExists('role_lookup');
        // Role Service Accesses
        Schema::dropIfExists('role_service_access');
        // Roles
        Schema::dropIfExists('role');
        // Email Templates
        Schema::dropIfExists('email_template');
        // System Lookup Keys
        Schema::dropIfExists('system_lookup');
        // Services
        Schema::dropIfExists('service');
        // Database Extras
        Schema::dropIfExists('db_table_extras');
        // Database Extras
        Schema::dropIfExists('db_field_extras');
        //Cors config table
        Schema::dropIfExists('cors_config');
        //Email service config table
        Schema::dropIfExists('email_config');
        //Email service parameters config table
        Schema::dropIfExists('email_parameters_config');
        // App relationship for user
        Schema::dropIfExists('user_to_app_role');
        // App Lookup Keys
        Schema::dropIfExists('app_lookup');
        //Apps to App Groups Relationships
        Schema::dropIfExists('app_to_app_group');
        // Application Groups
        Schema::dropIfExists('app_group');
        // Applications
        Schema::dropIfExists('app');
        //Password reset
        Schema::dropIfExists('password_resets');
        // User table
        Schema::dropIfExists('user');
    }
}
