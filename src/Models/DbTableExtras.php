<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by Userlicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Models;

/**
 * DbTableExtras
 *
 * @property integer    $id
 * @property integer    $service_id
 * @property string     $table
 * @property string     $label
 * @property string     $plural
 * @property string     $name_field
 * @property string     $description
 * @property string     $model
 * @method static \Illuminate\Database\Query\Builder|DbTableExtras whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|DbTableExtras whereServiceId( $value )
 */
class DbTableExtras extends BaseSystemModel
{
    protected $table = 'db_table_extras';

    public static function seed()
    {
        $seeded = false;

        $systemServiceId = Service::whereType( 'system' )->pluck( 'id' );
        if ( !static::whereServiceId( $systemServiceId )->count() )
        {
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'user',
                    'model'       => '\\DreamFactory\\Rave\\Models\\User',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'user_lookup',
                    'model'       => '\\DreamFactory\\Rave\\Models\\UserLookup',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'service',
                    'model'       => '\\DreamFactory\\Rave\\Models\\Service',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'service_type',
                    'model'       => '\\DreamFactory\\Rave\\Models\\ServiceType',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'service_doc',
                    'model'       => '\\DreamFactory\\Rave\\Models\\ServiceDoc',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'role',
                    'model'       => '\\DreamFactory\\Rave\\Models\\Role',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'role_service_access',
                    'model'       => '\\DreamFactory\\Rave\\Models\\RoleServiceAccess',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'role_lookup',
                    'model'       => '\\DreamFactory\\Rave\\Models\\RoleLookup',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'app',
                    'model'       => '\\DreamFactory\\Rave\\Models\\App',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'app_lookup',
                    'model'       => '\\DreamFactory\\Rave\\Models\\AppLookup',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'app_group',
                    'model'       => '\\DreamFactory\\Rave\\Models\\AppGroup',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'system_resource',
                    'model'       => '\\DreamFactory\\Rave\\Models\\SystemResource',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'script_type',
                    'model'       => '\\DreamFactory\\Rave\\Models\\ScriptType',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'event_script',
                    'model'       => '\\DreamFactory\\Rave\\Models\\EventScript',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'event_subscriber',
                    'model'       => '\\DreamFactory\\Rave\\Models\\EventSubscriber',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'email_template',
                    'model'       => '\\DreamFactory\\Rave\\Models\\EmailTemplate',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'system_setting',
                    'model'       => '\\DreamFactory\\Rave\\Models\\Setting',
                ]
            );
            static::create(
                [
                    'service_id'  => $systemServiceId,
                    'table'       => 'system_lookup',
                    'model'       => '\\DreamFactory\\Rave\\Models\\Lookup',
                ]
            );
            /*
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

                    // System Configuration
                    Schema::create(
                        'system_config',
                        function ( Blueprint $t )
                        {
                            $t->string( 'db_version', 32 )->primary();
                            $t->boolean( 'login_with_user_name' )->default( 0 );
                            $t->timestamp( 'created_date' );
                            $t->timestamp( 'last_modified_date' );
                            $t->integer( 'created_by_id' )->unsigned()->nullable();
                            $t->foreign( 'created_by_id' )->references( 'id' )->on( 'user' )->onDelete( 'set null' );
                            $t->integer( 'last_modified_by_id' )->unsigned()->nullable();
                            $t->foreign( 'last_modified_by_id' )->references( 'id' )->on( 'user' )->onDelete( 'set null' );
                        }
                    );

                    //Cors config table
                    Schema::create(
                        'cors_config',
                        function ( Blueprint $t )
                        {
                            $t->increments( 'id' );
                            $t->string( 'path' )->unique();
                            $t->string( 'origin' );
                            $t->longText( 'header' );
                            $t->integer( 'method' )->default( 0 );
                            $t->integer( 'max_age' )->default( 3600 );
                            $t->timestamp( 'created_date' );
                            $t->timestamp( 'last_modified_date' );
                            $t->integer( 'created_by_id' )->unsigned()->nullable();
                            $t->foreign( 'created_by_id' )->references( 'id' )->on( 'user' )->onDelete( 'set null' );
                            $t->integer( 'last_modified_by_id' )->unsigned()->nullable();
                            $t->foreign( 'last_modified_by_id' )->references( 'id' )->on( 'user' )->onDelete( 'set null' );
                        }
                    );

                    //Email service config table
                    Schema::create(
                        'email_config',
                        function ( Blueprint $t )
                        {
                            $t->integer( 'service_id' )->unsigned()->primary();
                            $t->foreign( 'service_id' )->references( 'id' )->on( 'service' )->onDelete( 'cascade' );
                            $t->string( 'driver' );
                            $t->string( 'host' )->nullable();
                            $t->string( 'port' )->nullable();
                            $t->string( 'encryption' )->default( 'tls' );
                            $t->longText( 'username' )->nullable(); //encrypted
                            $t->longText( 'password' )->nullable(); //encrypted
                            $t->string( 'command' )->default( '/usr/sbin/sendmail -bs' );
                            $t->longText( 'key' )->nullable(); //encrypted
                            $t->longText( 'secret' )->nullable(); //encrypted
                            $t->string( 'domain' )->nullable();
                        }
                    );

                    //Email service parameters config table
                    Schema::create(
                        'email_parameters_config',
                        function ( Blueprint $t )
                        {
                            $t->increments( 'id' );
                            $t->integer( 'service_id' )->unsigned();
                            $t->foreign( 'service_id' )->references( 'service_id' )->on( 'email_config' )->onDelete( 'cascade' );
                            $t->string( 'name' );
                            $t->mediumText( 'value' )->nullable();
                            $t->boolean( 'active' )->default( 1 );
                        }
                    );
           */
            $seeded = true;
        }

        return $seeded;
    }
}