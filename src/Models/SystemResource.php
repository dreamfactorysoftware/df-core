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
 * SystemResource
 *
 * @property string  $name
 * @property string  $class_name
 * @property string  $label
 * @property string  $description
 * @property boolean $singleton
 * @property boolean $read_only
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereLabel( $value )
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereSingleton( $value )
 * @method static \Illuminate\Database\Query\Builder|SystemResource whereReadOnly( $value )
 */
class SystemResource extends BaseModel
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

    protected $table = 'system_resource';

    protected $primaryKey = 'name';

    protected $fillable = [ 'name', 'label', 'description', 'singleton', 'class_name', 'model_name', 'read_only' ];

    public $incrementing = false;

    public static function seed()
    {
        $seeded = false;
        if ( !static::exists() )
        {
            $records = [
                [
                    'name'        => 'admin',
                    'label'       => 'Administrators',
                    'description' => 'Allows configuration of system administrators.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Admin',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\User',
                ],
                [
                    'name'        => 'cache',
                    'label'       => 'Cache Administration',
                    'description' => 'Allows administration of system-wide and service cache.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Cache'
                ],
                [
                    'name'        => 'config',
                    'label'       => 'Configuration',
                    'description' => 'Global system configuration.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Config',
                    'singleton'   => true,
                ],
                [
                    'name'        => 'constant',
                    'label'       => 'Constants',
                    'description' => 'Read-only listing of constants available for client use.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Constant',
                    'read_only'   => true,
                ],
                [
                    'name'        => 'cors',
                    'label'       => 'CORS Configuration',
                    'description' => 'Allows configuration of CORS system settings.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Cors',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\CorsConfig',
                ],
                [
                    'name'        => 'email_template',
                    'label'       => 'Email Templates',
                    'description' => 'Allows configuration of email templates.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\EmailTemplate',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\EmailTemplate',
                ],
                [
                    'name'        => 'environment',
                    'label'       => 'Environment',
                    'description' => 'Read-only system environment configuration.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Environment',
                    'singleton'   => true,
                    'read_only'   => true,
                ],
                [
                    'name'        => 'event',
                    'label'       => 'Events',
                    'description' => 'Allows registering server-side scripts to system generated events.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Event',
                ],
                [
                    'name'        => 'lookup',
                    'label'       => 'Lookup Keys',
                    'description' => 'Allows configuration of lookup keys.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Lookup',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\Lookup',
                ],
                [
                    'name'        => 'role',
                    'label'       => 'Roles',
                    'description' => 'Allows role configuration.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Role',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\Role',
                ],
                [
                    'name'        => 'service',
                    'label'       => 'Services',
                    'description' => 'Allows configuration of services.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Service',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\Service',
                ],
                [
                    'name'        => 'service_type',
                    'label'       => 'Service Types',
                    'description' => 'Read-only system service types.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\ServiceType',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\ServiceType',
                    'read_only'   => true,
                ],
                [
                    'name'        => 'script_type',
                    'label'       => 'Script Types',
                    'description' => 'Read-only system scripting types.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\ScriptType',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\ScriptType',
                    'read_only'   => true,
                ],
                [
                    'name'        => 'setting',
                    'label'       => 'Custom Settings',
                    'description' => 'Allows configuration of system-wide custom settings.',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Setting',
                    'model_name'  => 'DreamFactory\\Rave\\Models\\Setting',
                ],
                [
                    'name'        => 'app',
                    'label'       => 'Apps',
                    'description' => 'Allows management of user application(s)',
                    'class_name'  => "DreamFactory\\Rave\\Resources\\System\\App",
                    'model_name'  => 'DreamFactory\\Rave\\Models\\App',
                ],
                [
                    'name'        => 'app_group',
                    'label'       => 'App Groups',
                    'description' => 'Allows grouping of user application(s)',
                    'class_name'  => "DreamFactory\\Rave\\Resources\\System\\AppGroup",
                    'model_name'  => 'DreamFactory\\Rave\\Models\\AppGroup',
                ],
            ];

            foreach ( $records as $record )
            {
                static::create( $record );
            }

            $seeded = true;
        }

        return $seeded;
    }
}