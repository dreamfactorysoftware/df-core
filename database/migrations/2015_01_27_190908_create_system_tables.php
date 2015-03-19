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
            'service_types',
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

        // Services
        Schema::create(
            'services',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 40 )->unique();
                $t->string( 'label', 80 );
                $t->string( 'description' )->nullable();
                $t->boolean( 'is_active' )->default( 0 );
                $t->string( 'type' );
                $t->foreign( 'type' )->references( 'name' )->on( 'service_types' )->onDelete( 'cascade' );
                $t->nullableTimestamps();
                // TODO Override behavior later, currently Laravel blatantly sets default 0 for timestamp
//                $t->timestamp( 'created_date' )->default('CURRENT_TIMESTAMP');
//                $t->timestamp( 'last_modified_date' )->default('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');
            }
        );

        // System Resources
        Schema::create(
            'system_resources',
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

        // Roles
        Schema::create(
            'roles',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 64 )->unique();
                $t->string( 'description' )->nullable();
                $t->boolean( 'is_active' )->default( 0 );
                $t->nullableTimestamps();
            }
        );

        // Roles to Services Allowed Accesses
        Schema::create(
            'role_service_accesses',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 64 )->unique();
                $t->string( 'description' )->nullable();
                $t->boolean( 'is_active' )->default( 0 );
                $t->integer( 'role_id' )->unsigned();
                $t->foreign( 'role_id' )->references( 'id' )->on( 'roles' )->onDelete( 'cascade' );
                $t->integer( 'service_id' )->unsigned()->nullable();
                $t->foreign( 'service_id' )->references( 'id' )->on( 'services' )->onDelete( 'cascade' );
                $t->string( 'component' )->nullable();
                $t->integer( 'verb_mask' )->unsigned()->default( 0 );
                $t->integer( 'requestor_mask' )->unsigned()->default( 0 );
                $t->text( 'filters' )->nullable();
                $t->string( 'filter_op', 32 )->default( 'and' );
                $t->nullableTimestamps();
            }
        );

        // Roles to System Resources Allowed Accesses
        Schema::create(
            'role_system_accesses',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name', 64 )->unique();
                $t->string( 'description' )->nullable();
                $t->boolean( 'is_active' )->default( 0 );
                $t->integer( 'role_id' )->unsigned();
                $t->foreign( 'role_id' )->references( 'id' )->on( 'roles' )->onDelete( 'cascade' );
                $t->string( 'component' )->nullable();
                $t->integer( 'verb_mask' )->unsigned()->default( 0 );
                $t->integer( 'requestor_mask' )->unsigned()->default( 0 );
                $t->text( 'filters' )->nullable();
                $t->string( 'filter_op', 32 )->default( 'and' );
                $t->nullableTimestamps();
            }
        );

        // Role Lookup Keys
        Schema::create(
            'role_lookups',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->integer( 'role_id' )->unsigned();
                $t->foreign( 'role_id' )->references( 'id' )->on( 'roles' )->onDelete( 'cascade' );
                $t->string( 'name' )->index();
                $t->text( 'value' )->nullable();
                $t->boolean( 'private' )->default( 0 );
                $t->nullableTimestamps();
            }
        );

        // Users - Admins for system
        Schema::create(
            'users',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'user_name', 40 )->unique();
                $t->string( 'email' )->unique();
                $t->string( 'password', 64 );
                $t->string( 'name' );
                $t->boolean( 'is_sys_admin' )->default( 0 );
                $t->boolean( 'is_active' )->default( 0 );
                $t->dateTime( 'last_login' )->nullable();
                $t->rememberToken();
                $t->nullableTimestamps();
            }
        );

        Schema::create(
            'password_resets',
            function ( Blueprint $table )
            {
                $table->string( 'email' )->index();
                $table->string( 'token' )->index();
                $table->timestamp( 'created_at' );
            }
        );

        // System Settings
        Schema::create(
            'settings',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name' )->unique();
                $t->text( 'value' )->nullable();
                $t->nullableTimestamps();
            }
        );

        // System Lookups
        Schema::create(
            'lookups',
            function ( Blueprint $t )
            {
                $t->increments( 'id' );
                $t->string( 'name' )->unique();
                $t->text( 'value' )->nullable();
                $t->boolean( 'private' )->default( 0 );
                $t->string( 'description' )->nullable();
                $t->nullableTimestamps();
            }
        );

        // Email Templates
        Schema::create(
            'email_templates',
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
                $t->nullableTimestamps();
            }
        );

        // System Configuration
        Schema::create(
            'config',
            function ( Blueprint $t )
            {
                $t->string( 'db_version', 32 )->primary();
                $t->boolean( 'login_with_user_name' )->default( 0 );
                $t->string( 'api_key' )->nullable();
                $t->boolean( 'allow_guest_access' )->default( 0 );
                $t->integer( 'guest_role_id' )->unsigned()->nullable();
                $t->foreign( 'guest_role_id' )->references( 'id' )->on( 'roles' )->onDelete( 'set null' );
                $t->nullableTimestamps();
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

        // System Configuration
        Schema::dropIfExists( 'config' );
        // Role Lookup Keys
        Schema::dropIfExists( 'role_lookups' );
        // Role Service Accesses
        Schema::dropIfExists( 'role_service_accesses' );
        // Role System Accesses
        Schema::dropIfExists( 'role_system_accesses' );
        // Roles
        Schema::dropIfExists( 'roles' );
        // Email Templates
        Schema::dropIfExists( 'email_templates' );
        // System Custom Settings
        Schema::dropIfExists( 'settings' );
        // System Lookup Keys
        Schema::dropIfExists( 'lookups' );
        // Services
        Schema::dropIfExists( 'services' );
        // Service Types
        Schema::dropIfExists( 'service_types' );
        // Password Resets for Users
        Schema::drop( 'password_resets' );
        // Users
        Schema::dropIfExists( 'users' );
    }

}
