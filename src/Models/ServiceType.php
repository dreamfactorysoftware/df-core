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
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace DreamFactory\Rave\Models;

/**
 * ServiceType
 *
 * @property string  $name
 * @property string  $class_name
 * @property string  $config_handler
 * @property string  $label
 * @property string  $description
 * @property string  $group
 * @property boolean $singleton
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereLabel( $value )
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereSingleton( $value )
 * @method static \Illuminate\Database\Query\Builder|ServiceType whereGroup( $value )
 */
class ServiceType extends BaseModel
{
    protected $table = 'service_type';

    protected $primaryKey = 'name';

    protected $fillable = [ 'name', 'class_name', 'config_handler', 'label', 'description', 'group', 'singleton' ];

    public $timestamps = false;

    public $incrementing = false;

    public static function seed()
    {
        $seeded = false;

        if ( !static::whereName( 'local_file' )->count() )
        {
            $records = [
                [
                    "name"           => "local_file",
                    "class_name"     => "DreamFactory\\Rave\\Services\\LocalFileService",
                    "config_handler" => null,
                    "label"          => "Local file service",
                    "description"    => "File service supporting the local file system.",
                    "group"          => "files",
                    "singleton"      => 1
                ]
            ];

            foreach ( $records as $record )
            {
                static::create( $record );
            }
            $seeded = true;
        }

        if ( !static::whereName( 'local_email' )->exists() )
        {
            static::create(
                [
                    'name'           => 'local_email',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Email\\Local',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\EmailServiceConfig',
                    'label'          => 'Email service',
                    'description'    => 'Local Email service',
                    'group'          => 'emails',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'smtp_email' )->exists() )
        {
            static::create(
                [
                    'name'           => 'smtp_email',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Email\\Smtp',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\EmailServiceConfig',
                    'label'          => 'Email service',
                    'description'    => 'Smtp Email service',
                    'group'          => 'emails',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'mailgun' )->exists() )
        {
            static::create(
                [
                    'name'           => 'mailgun',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Email\\Mailgun',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\EmailServiceConfig',
                    'label'          => 'Email service',
                    'description'    => 'Mailgun Email service',
                    'group'          => 'emails',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'mandrill' )->exists() )
        {
            static::create(
                [
                    'name'           => 'mandrill',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Email\\Mandrill',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\EmailServiceConfig',
                    'label'          => 'Email service',
                    'description'    => 'Mandrill Email service',
                    'group'          => 'emails',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        return $seeded;
    }

}