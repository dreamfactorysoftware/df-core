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
    protected $table = 'system_resource';

    protected $primaryKey = 'name';

    protected $fillable = [ 'name', 'label', 'description', 'singleton', 'class_name' ];

    public $timestamps = false;

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
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Admin',
                    'description' => 'Allows configuration of system administrators.',
                ],
                [
                    'name'        => 'config',
                    'label'       => 'Configuration',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Config',
                    'description' => 'Global system configuration.',
                    'singleton'   => true,
                ],
                [
                    'name'        => 'constant',
                    'label'       => 'Constants',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Constant',
                    'description' => 'Read-only listing of constants available for client use.',
                    'read_only'   => true,
                ],
                [
                    'name'        => 'cors',
                    'label'       => 'CORS Configuration',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Cors',
                    'description' => 'Allows configuration of CORS system settings.',
                ],
                [
                    'name'        => 'email_template',
                    'label'       => 'Email Templates',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\EmailTemplate',
                    'description' => 'Allows configuration of email templates.',
                ],
                [
                    'name'        => 'environment',
                    'label'       => 'Environment',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Environment',
                    'description' => 'Read-only system environment configuration.',
                    'singleton'   => true,
                    'read_only'   => true,
                ],
                [
                    'name'        => 'lookup',
                    'label'       => 'Lookup Keys',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Lookup',
                    'description' => 'Allows configuration of lookup keys.',
                ],
                [
                    'name'        => 'role',
                    'label'       => 'Roles',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Role',
                    'description' => 'Allows role configuration.',
                ],
                [
                    'name'        => 'service',
                    'label'       => 'Services',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Service',
                    'description' => 'Allows configuration of services.',
                ],
                [
                    'name'        => 'setting',
                    'label'       => 'Custom Settings',
                    'class_name'  => 'DreamFactory\\Rave\\Resources\\System\\Setting',
                    'description' => 'Allows configuration of system-wide custom settings.',
                ],
                [
                    'name'        => 'app',
                    'label'       => 'Application Management',
                    'class_name'  => "DreamFactory\\Rave\\Resources\\System\\App",
                    'description' => 'Allows managemnt of user application(s)'
                ]
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