<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSystemTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Service Types
        Schema::create(
            'service_type',
            function ( Blueprint $t )
            {
                $t->string( 'name', 40 )->primary();
                $t->string( 'class_name' );
                $t->string( 'config_handler' )->nullable();
                $t->string( 'label', 80 );
                $t->string( 'description' )->nullable();
                $t->string( 'group' )->nullable();
                $t->boolean( 'singleton' )->default( 0 );
            }
        );

        // System Resources
        Schema::create(
            'system_resource',
            function ( Blueprint $t )
            {
                $t->string( 'name', 40 )->primary();
                $t->string( 'class_name' );
                $t->string( 'label', 80 );
                $t->string( 'description' )->nullable();
                $t->boolean( 'singleton' )->default( 0 );
                $t->boolean( 'read_only' )->default( 0 );
            }
        );

        // Services
        Schema::create(
            'service',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 40 )->unique();
                $t->string( 'label', 80 );
                $t->string( 'description' )->nullable();
                $t->boolean( 'is_active' )->default( 0 );
                $t->string( 'type' );
                $t->foreign( 'type' )->references( 'name' )->on( 'service_type' )->onDelete( 'cascade' );
                $t->boolean( 'mutable' )->default( 1 );
                $t->boolean( 'deletable' )->default( 1 );
                $t->timestamps();
            }
        );

        // Service API Docs
        Schema::create(
            'service_doc',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'service_id' )->unsigned();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'service' )->onDelete( 'cascade' );
                $t->integer( 'format' )->unsigned()->default( 0 );
                $t->text( 'content' )->nullable();
            }
        );

        // Script Types
        Schema::create(
            'script_type',
            function ( Blueprint $t )
            {
                $t->string( 'name', 40 )->primary();
                $t->string( 'class_name' );
                $t->string( 'label', 80 );
                $t->string( 'description' )->nullable();
                $t->boolean( 'sandboxed' )->default( 0 );
            }
        );

        // Script Service Config
        Schema::create(
            'script_config',
            function ( Blueprint $t )
            {
                $t->integer( 'service_id' )->unsigned()->primary();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'service' )->onDelete( 'cascade' );
                $t->string( 'type' );
                $t->foreign( 'type' )->references( 'name' )->on( 'script_type' )->onDelete( 'cascade' );
                $t->text( 'content' )->nullable();
                $t->text( 'config' )->nullable();
            }
        );

        // Event Scripts
        Schema::create(
            'event_script',
            function ( Blueprint $t )
            {
                $t->string( 'name', 80 )->primary();
                $t->string( 'type' );
                $t->foreign( 'type' )->references( 'name' )->on( 'script_type' )->onDelete( 'cascade' );
                $t->text( 'content' )->nullable();
                $t->text( 'config' )->nullable();
            }
        );

        // Roles
        Schema::create(
            'role',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 64 )->unique();
                $t->string( 'description' )->nullable();
                $t->boolean( 'is_active' )->default( 0 );
                $t->timestamps();
            }
        );

        // Roles to Services Allowed Accesses
        Schema::create(
            'role_service_access',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'role_id' )->unsigned();
                $t->foreign( 'role_id' )->references( 'id' )->on( 'role' )->onDelete( 'cascade' );
                $t->integer( 'service_id' )->unsigned()->nullable();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'service' )->onDelete( 'cascade' );
                $t->string( 'component' )->nullable();
                $t->integer( 'verb_mask' )->unsigned()->default( 0 );
                $t->integer( 'requestor_mask' )->unsigned()->default( 0 );
                $t->text( 'filters' )->nullable();
                $t->string( 'filter_op', 32 )->default( 'and' );
            }
        );

        // Role Lookup Keys
        Schema::create(
            'role_lookup',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'role_id' )->unsigned();
                $t->foreign( 'role_id' )->references( 'id' )->on( 'role' )->onDelete( 'cascade' );
                $t->string( 'name' )->index();
                $t->text( 'value' )->nullable();
                $t->boolean( 'private' )->default( 0 );
                $t->text( 'description' )->nullable();
                $t->timestamps();
            }
        );

        // System Settings
        Schema::create(
            'system_setting',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name' )->unique();
                $t->text( 'value' )->nullable();
                $t->timestamps();
            }
        );

        // System Lookups
        Schema::create(
            'system_lookup',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name' )->unique();
                $t->text( 'value' )->nullable();
                $t->boolean( 'private' )->default( 0 );
                $t->text( 'description' )->nullable();
                $t->timestamps();
            }
        );

        // Email Templates
        Schema::create(
            'email_template',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 64 )->unique();
                $t->string( 'description' )->nullable();
                $t->text( 'to' )->nullable();
                $t->text( 'cc' )->nullable();
                $t->text( 'bcc' )->nullable();
                $t->string( 'subject', 80 )->nullable();
                $t->text( 'body_text' )->nullable();
                $t->text( 'body_html' )->nullable();
                $t->string( 'from_name', 80 )->nullable();
                $t->string( 'from_email' )->nullable();
                $t->string( 'reply_to_name', 80 )->nullable();
                $t->string( 'reply_to_email' )->nullable();
                $t->text( 'defaults' )->nullable();
                $t->timestamps();
            }
        );

        // Database Services Extras
        Schema::create(
            'db_service_extras',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'service_id' )->unsigned()->nullable();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'service' )->onDelete( 'cascade' );
                $t->string( 'table', 128 );
                $t->string( 'field', 128 )->default( '' );
                $t->string( 'label', 128 )->default( '' );
                $t->string( 'plural', 128 )->default( '' );
                $t->string( 'name_field', 128 )->default( '' );
                $t->text( 'picklist' )->nullable();
                $t->text( 'validation' )->nullable();
                $t->boolean( 'user_id' )->default( 0 );
                $t->boolean( 'user_id_on_update' )->nullable();
                $t->boolean( 'timestamp_on_update' )->nullable();
            }
        );

        // System Configuration
        Schema::create(
            'system_config',
            function ( Blueprint $t )
            {
                $t->string( 'db_version', 32 )->primary();
                $t->boolean( 'login_with_user_name' )->default( 0 );
                $t->timestamps();
            }
        );

        // Users table
        Schema::create('users', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password', 60);
            $table->boolean('is_sys_admin')->default(0);
            $table->boolean('is_active')->default(1);
            $table->rememberToken();
            $table->timestamps();
        });

        //Password reset table
        Schema::create('password_resets', function(Blueprint $table)
        {
            $table->string('email')->index();
            $table->string('token')->index();
            $table->timestamp('created_at');
        });

        //Cors config table
        Schema::create(
            'cors_config',
            function(Blueprint $t)
            {
                $t->increments('id');
                $t->string('path');
                $t->unique('path');
                $t->string('origin');
                $t->longText('header');
                $t->integer('method')->default(0);
                $t->integer('max_age')->default(3600);
                $t->timestamps();
            }
        );

        //Email service config table
        Schema::create(
            'email_config',
            function ( Blueprint $t )
            {
                $t->integer( 'service_id' )->unsigned()->primary();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'service' )->onDelete( 'cascade' );
                $t->string('driver');
                $t->string('host')->nullable();
                $t->string('port')->nullable();
                $t->string('encryption')->default('tls');
                $t->longText('username')->nullable(); //encrypted
                $t->longText('password')->nullable(); //encrypted
                $t->string('command')->default('/usr/sbin/sendmail -bs');
                $t->longText('key')->nullable(); //encrypted
                $t->longText('secret')->nullable(); //encrypted
                $t->string('domain')->nullable();
                $t->timestamps();
            }
        );

        //Email service parameters config table
        Schema::create(
            'email_parameters_config',
            function( Blueprint $t)
            {
                $t->increments('id');
                $t->integer('service_id')->unsigned();
                $t->foreign('service_id')->references('service_id')->on('email_config')->onDelete('cascade');
                $t->string('name');
                $t->mediumText('value')->nullable();
                $t->boolean('active')->default(1);
                $t->timestamps();
            }
        );

        // Applications
        Schema::create(
            'app',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 64 )->unique();
                $t->string( 'api_key' )->nullable();
                $t->string( 'description' )->nullable();
                $t->boolean( 'is_active' )->default( 0 );
                $t->integer( 'type' )->unsigned()->default( 0 );
                $t->text( 'path' )->nullable();
                $t->text( 'url' )->nullable();
                $t->integer( 'storage_service_id' )->unsigned()->nullable();
                $t->foreign( 'storage_service_id' )->references( 'id' )->on( 'service' )->onDelete( 'set null' );
                $t->string( 'storage_container', 255 )->nullable();
                $t->text( 'import_url' )->nullable();
                $t->boolean( 'requires_fullscreen' )->default( 0 );
                $t->boolean( 'allow_fullscreen_toggle' )->default( 1 );
                $t->string( 'toggle_location', 64 )->default( 'top' );
                $t->integer( 'role_id' )->unsigned()->nullable();
                $t->foreign( 'role_id' )->references( 'id' )->on( 'role' )->onDelete( 'set null' );
                $t->timestamps();
            }
        );

        // App Lookup Keys
        Schema::create(
            'app_lookup',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'app_id' )->unsigned();
                $t->foreign( 'app_id' )->references( 'id' )->on( 'app' )->onDelete( 'cascade' );
                $t->string( 'name' )->index();
                $t->text( 'value' )->nullable();
                $t->boolean( 'private' )->default( 0 );
                $t->timestamps();
            }
        );

        // Application Groups - visual aid for Launchpad only
        Schema::create(
            'app_group',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 64 )->unique();
                $t->string( 'description' )->nullable();
                $t->timestamps();
            }
        );

        // Apps to App Groups Relationships - visual aid for Launchpad only
        Schema::create(
            'app_to_app_group',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'app_id' )->unsigned()->nullable();
                $t->foreign( 'app_id' )->references( 'id' )->on( 'app' )->onDelete( 'cascade' );
                $t->integer( 'group_id' )->unsigned()->nullable();
                $t->foreign( 'group_id' )->references( 'id' )->on( 'app_group' )->onDelete( 'cascade' );
            }
        );

        // App relationship for user
        Schema::create(
            'user_to_app_role',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'user_id' )->unsigned();
                $t->foreign( 'user_id' )->references( 'id' )->on( 'users' )->onDelete( 'cascade' );
                $t->integer( 'app_id' )->unsigned()->nullable();
                $t->foreign( 'app_id' )->references( 'id' )->on( 'app' )->onDelete( 'cascade' );
                $t->integer( 'role_id' )->unsigned()->nullable();
                $t->foreign( 'role_id' )->references( 'id' )->on( 'role' )->onDelete( 'set null' );
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

        // Service Docs
        Schema::dropIfExists( 'service_doc' );
        // Scripts
        Schema::dropIfExists( 'script' );
        // System Configuration
        Schema::dropIfExists( 'system_config' );
        // Role Lookup Keys
        Schema::dropIfExists( 'role_lookup' );
        // Role Service Accesses
        Schema::dropIfExists( 'role_service_access' );
        // Roles
        Schema::dropIfExists( 'role' );
        // Email Templates
        Schema::dropIfExists( 'email_template' );
        // System Custom Settings
        Schema::dropIfExists( 'system_setting' );
        // System Lookup Keys
        Schema::dropIfExists( 'system_lookup' );
        // Services
        Schema::dropIfExists( 'service' );
        // System Resources
        Schema::dropIfExists( 'system_resource' );
        // Service Types
        Schema::dropIfExists( 'service_type' );
        // Users table
        Schema::dropIfExists( 'users' );
        //Password reset
        Schema::dropIfExists( 'password_resets' );
        //Cors config table
        Schema::dropIfExists('cors_config');
        //Email service config table
        Schema::dropIfExists('email_config');
        //Email service parameters config table
        Schema::dropIfExists('email_parameters_config');
        // App relationship for user
        Schema::dropIfExists( 'user_to_app_role' );
        // App Lookup Keys
        Schema::dropIfExists( 'app_lookup' );
        //Apps to App Groups Relationships
        Schema::dropIfExists( 'app_to_app_group' );
        // Application Groups
        Schema::dropIfExists( 'app_group' );
        // Applications
        Schema::dropIfExists( 'app' );
    }
}
