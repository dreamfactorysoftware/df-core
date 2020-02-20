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
        // Even though we take care of this scenario in the code,
        // SQL Server does not allow potential cascading loops,
        // so set the default no action and clear out created/modified by another user when deleting a user.
        $onDelete = (('sqlsrv' === $driver) ? 'no action' : 'set null');

        $output = new ConsoleOutput();
        $output->writeln("Migration driver used: $driver");

        // User table
        Schema::create(
            'user',
            function (Blueprint $t) use ($onDelete){
                $t->increments('id');
                $t->string('name');
                $t->string('first_name')->nullable();
                $t->string('last_name')->nullable();
                $t->dateTime('last_login_date')->nullable();
                $t->string('email')->unique();
                $t->text('password')->nullable();
                $t->boolean('is_sys_admin')->default(0);
                $t->boolean('is_active')->default(1);
                $t->string('phone', 32)->nullable();
                $t->string('security_question')->nullable();
                $t->text('security_answer')->nullable();
                $t->string('confirm_code')->nullable();
                $t->integer('default_app_id')->unsigned()->nullable();
                $t->rememberToken();
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            }
        );

        // Password reset table
        Schema::create(
            'password_resets',
            function (Blueprint $t){
                $t->string('email')->index();
                $t->string('token')->index();
                $t->timestamp('created_date')->nullable();
            }
        );

        // Services
        Schema::create(
            'service',
            function (Blueprint $t) use ($onDelete){
                $t->increments('id');
                $t->string('name', 40)->unique();
                $t->string('label', 80);
                $t->string('description')->nullable();
                $t->boolean('is_active')->default(0);
                $t->string('type', 40);
                $t->boolean('mutable')->default(1);
                $t->boolean('deletable')->default(1);
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            }
        );

        // Service API Docs
        Schema::create(
            'service_doc',
            function (Blueprint $t){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->integer('format')->unsigned()->default(0);
                $t->mediumText('content')->nullable();
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
            }
        );

        // Roles
        Schema::create(
            'role',
            function (Blueprint $t) use ($onDelete){
                $t->increments('id');
                $t->string('name', 64)->unique();
                $t->string('description')->nullable();
                $t->boolean('is_active')->default(0);
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            }
        );

        // Roles to Services Allowed Accesses
        Schema::create(
            'role_service_access',
            function (Blueprint $t) use ($onDelete){
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
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            }
        );

        // Email Templates
        Schema::create(
            'email_template',
            function (Blueprint $t) use ($onDelete){
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
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            }
        );

        //Cors config table
        Schema::create(
            'cors_config',
            function (Blueprint $t) use ($onDelete){
                $t->increments('id');
                $t->string('path')->unique();
                $t->string('origin')->nullable();
                $t->text('header')->nullable();
                $t->integer('method')->unsigned()->default(0);
                $t->integer('max_age')->unsigned()->default(0);
                $t->boolean('enabled')->default(true);
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            }
        );

        // Applications
        Schema::create(
            'app',
            function (Blueprint $t) use ($onDelete){
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
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
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
                $t->integer('cache_ttl')->unsigned()->default(0);
            }
        );

        // System Configuration
        Schema::create(
            'system_config',
            function (Blueprint $t) use ($onDelete){
                $t->integer('service_id')->unsigned()->primary();
                $t->foreign('service_id')->references('id')->on('service')->onDelete('cascade');
                $t->integer('default_app_id')->unsigned()->nullable();
                $t->foreign('default_app_id')->references('id')->on('app')->onDelete('set null');
                $t->integer('invite_email_service_id')->unsigned()->nullable();
                $t->foreign('invite_email_service_id')->references('id')->on('service')->onDelete($onDelete);
                $t->integer('invite_email_template_id')->unsigned()->nullable();
                $t->foreign('invite_email_template_id')->references('id')->on('email_template')->onDelete($onDelete);
                $t->integer('password_email_service_id')->unsigned()->nullable();
                $t->foreign('password_email_service_id')->references('id')->on('service')->onDelete($onDelete);
                $t->integer('password_email_template_id')->unsigned()->nullable();
                $t->foreign('password_email_template_id')->references('id')->on('email_template')->onDelete($onDelete);
            }
        );

        // create system customizations
        Schema::create(
            'system_custom',
            function (Blueprint $t) use ($onDelete){
                $t->string('name')->primary();
                $t->mediumText('value')->nullable();
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
            }
        );

        // Lookups
        Schema::create(
            'lookup',
            function (Blueprint $t) use ($onDelete){
                $t->increments('id');
                $t->integer('app_id')->unsigned()->nullable();
                $t->foreign('app_id')->references('id')->on('app')->onDelete('cascade');
                $t->integer('role_id')->unsigned()->nullable();
                $t->foreign('role_id')->references('id')->on('role')->onDelete('cascade');
                $t->integer('user_id')->unsigned()->nullable();
                $t->foreign('user_id')->references('id')->on('user')->onDelete('cascade');
                $t->string('name');
                $t->text('value')->nullable();
                $t->boolean('private')->default(0);
                $t->text('description')->nullable();
                $t->timestamp('created_date')->nullable();
                $t->timestamp('last_modified_date')->useCurrent();
                $t->integer('created_by_id')->unsigned()->nullable();
                $t->foreign('created_by_id')->references('id')->on('user')->onDelete($onDelete);
                $t->integer('last_modified_by_id')->unsigned()->nullable();
                $t->foreign('last_modified_by_id')->references('id')->on('user')->onDelete($onDelete);
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

        // Lookup Keys
        Schema::dropIfExists('lookup');
        // System customizations
        Schema::dropIfExists('system_custom');
        // System Configuration
        Schema::dropIfExists('system_config');
        // Cache-able service configuration
        Schema::dropIfExists('service_cache_config');
        // File storage, public path designation
        Schema::dropIfExists('file_service_config');
        // JSON Web Token to system resources map
        Schema::dropIfExists('token_map');
        // App relationship for user
        Schema::dropIfExists('user_to_app_to_role');
        // Applications
        Schema::dropIfExists('app');
        //Cors config table
        Schema::dropIfExists('cors_config');
        // Email Templates
        Schema::dropIfExists('email_template');
        // Role Service Accesses
        Schema::dropIfExists('role_service_access');
        // Roles
        Schema::dropIfExists('role');
        // Service Docs
        Schema::dropIfExists('service_doc');
        // Services
        Schema::dropIfExists('service');
        //Password reset
        Schema::dropIfExists('password_resets');
        // User table
        Schema::dropIfExists('user');
    }
}
