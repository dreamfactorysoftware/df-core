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
 * Role
 *
 * @property integer $id
 * @property string  $api_name
 * @property string  $name
 * @property string  $description
 * @property boolean $is_active
 * @property integer $type_id
 * @property integer $native_format_id
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Role whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereApiName( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereDescription( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereIsActive( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereTypeId( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereNativeFormatId( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|Role whereLastModifiedDate( $value )
 */
class Role extends BaseSystemModel
{
    protected $table = 'role';

    protected $fillable = ['name', 'description', 'is_active'];

    protected $appends = ['role_service_accesses', 'role_system_accesses'];

    protected $role_service_accesses = [];

    protected $role_system_accesses = [];

    public static function boot()
    {
        parent::boot();

        static::created(
            function ( Role $role )
            {
                if ( !empty( $role->role_service_accesses ) )
                {
//                    $serviceAccess = [];
//                    foreach($role->parameters as $param)
//                    {
//                        $params[] = new ParameterConfig($param);
//                    }
//                    $rwsConfig->parameter()->saveMany($params);
                }

                if ( !empty( $role->role_system_accesses ) )
                {
//                    $headers = [];
//                    foreach($rwsConfig->headers as $header)
//                    {
//                        $headers[] = new HeaderConfig($header);
//                    }
//                    $rwsConfig->header()->saveMany($headers);
                }

                return true;
            }
        );
    }

    public function serviceAccess()
    {
        $this->hasMany('DreamFactory\Rave\Models\RoleServiceAccess');
    }

    public function systemAccess()
    {
        $this->hasMany('DreamFactory\Rave\Models\RoleSystemAccess');
    }

}