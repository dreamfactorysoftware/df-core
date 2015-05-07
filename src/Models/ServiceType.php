<?php
/**
 * This file is part of the DreamFactory Rave(tm)
 *
 * DreamFactory Rave(tm) <http://github.com/dreamfactorysoftware/rave>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
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
    /**
     * The name of the "created at" column.
     *
     * @var string
     */
    const CREATED_AT = 'created_date';

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'last_modified_date';

    protected $table = 'service_type';

    protected $primaryKey = 'name';

    protected $fillable = [ 'name', 'class_name', 'config_handler', 'label', 'description', 'group', 'singleton' ];

    public $incrementing = false;

    public static function seed()
    {
        $seeded = false;

        if ( !static::whereName( 'system' )->count() )
        {
            static::create(
                [
                    'name'           => 'system',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\System',
                    'config_handler' => null,
                    'label'          => 'System Management Service',
                    'description'    => 'Service supporting management of the RAVE system.',
                    'group'          => 'system',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'swagger' )->count() )
        {
            static::create(
                [
                    'name'           => 'swagger',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Swagger',
                    'config_handler' => null,
                    'label'          => 'Swagger API Docs',
                    'description'    => 'API documenting and testing service using Swagger specifications.',
                    'group'          => 'api_doc',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'event' )->count() )
        {
            static::create(
                [
                    'name'           => 'event',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Event',
                    'config_handler' => null,
                    'label'          => 'Event Service',
                    'description'    => 'Service that allows clients to subscribe to system broadcast events.',
                    'group'          => 'event',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'script' )->count() )
        {
            static::create(
                [
                    'name'           => 'script',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Script',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\ScriptConfig',
                    'label'          => 'Custom Scripting Service',
                    'description'    => 'Service that allows client-callable scripts utilizing the system scripting.',
                    'group'          => 'script',
                    'singleton'      => 0
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'local_file' )->count() )
        {
            static::create(
                [
                    'name'           => 'local_file',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\LocalFileService',
                    'config_handler' => null,
                    'label'          => 'Local File Service',
                    'description'    => 'File service supporting the local file system.',
                    'group'          => 'file',
                    'singleton'      => 1
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'local_email' )->exists() )
        {
            static::create(
                [
                    'name'           => 'local_email',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Email\\Local',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\EmailServiceConfig',
                    'label'          => 'Local Email Service',
                    'description'    => 'Local email service using system configuration.',
                    'group'          => 'email',
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
                    'label'          => 'SMTP Email Service',
                    'description'    => 'SMTP-based email service',
                    'group'          => 'email',
                    'singleton'      => 0
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'mailgun_email' )->exists() )
        {
            static::create(
                [
                    'name'           => 'mailgun_email',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Email\\Mailgun',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\EmailServiceConfig',
                    'label'          => 'Mailgun Email Service',
                    'description'    => 'Mailgun email service',
                    'group'          => 'email',
                    'singleton'      => 0
                ]
            );
            $seeded = true;
        }

        if ( !static::whereName( 'mandrill_email' )->exists() )
        {
            static::create(
                [
                    'name'           => 'mandrill_email',
                    'class_name'     => 'DreamFactory\\Rave\\Services\\Email\\Mandrill',
                    'config_handler' => 'DreamFactory\\Rave\\Models\\EmailServiceConfig',
                    'label'          => 'Mandrill Email Service',
                    'description'    => 'Mandrill email service',
                    'group'          => 'email',
                    'singleton'      => 0
                ]
            );
            $seeded = true;
        }

        return $seeded;
    }

}