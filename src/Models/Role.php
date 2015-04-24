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

use \Cache;
use DreamFactory\Library\Utility\ArrayUtils;

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

    protected $fillable = [ 'name', 'description', 'is_active' ];

    protected static $relatedModels = [
        'role_service_access' => 'DreamFactory\Rave\Models\RoleServiceAccess',
        'role_lookup'         => 'DreamFactory\Rave\Models\RoleLookup',
        'app'                 => 'DreamFactory\Rave\Models\App',
        'system_config'       => 'DreamFactory\Rave\Models\Config',
        'service'             => 'DreamFactory\Rave\Models\Service'
    ];

    public static function boot()
    {
        parent::boot();

        static::saved(
            function ( Role $role )
            {
                if ( Cache::has( 'role_' . $role->id ) )
                {
                    Cache::forget( 'role_' . $role->id );
                }
            }
        );
    }

    /**
     * @return array
     */
    public function getRoleServiceAccess()
    {
        $rsa = $this->getRelation( 'role_service_access_by_role_id' )->toArray();
        $services = $this->getRelation( 'service_by_role_service_access' )->toArray();

        foreach ( $rsa as $key => $s )
        {
            $serviceName = ArrayUtils::findByKeyValue( $services, 'id', ArrayUtils::get( $s, 'service_id' ), 'name' );
            ArrayUtils::set( $rsa[$key], 'service_name', $serviceName );
        }

        return $rsa;
    }

//    public static function seed()
//    {
//        $seeded = false;
//
//        if ( !static::whereId( 1 )->exists() )
//        {
//            static::create(
//                [
//                    'id'                      => 1,
//                    'name'                    => 'default',
//                    'description'             => 'A default system role.',
//                    'is_active'               => 1
//                ]
//            );
//            $seeded = true;
//        }
//
//        return $seeded;
//    }
}