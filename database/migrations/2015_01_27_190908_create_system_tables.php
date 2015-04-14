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
                $t->string( 'api_key' )->nullable();
                $t->boolean( 'allow_guest_access' )->default( 0 );
                $t->integer( 'guest_role_id' )->unsigned()->nullable();
                $t->foreign( 'guest_role_id' )->references( 'id' )->on( 'role' )->onDelete( 'set null' );
                $t->timestamps();
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
    }
}
