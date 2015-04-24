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

use \Cache;

/**
 * Config
 *
 * @property integer $id
 * @property string  $name
 * @property string  $value
 * @property string  $created_date
 * @property string  $last_modified_date
 * @method static \Illuminate\Database\Query\Builder|Config whereId( $value )
 * @method static \Illuminate\Database\Query\Builder|Config whereName( $value )
 * @method static \Illuminate\Database\Query\Builder|Config whereValue( $value )
 * @method static \Illuminate\Database\Query\Builder|Config whereCreatedDate( $value )
 * @method static \Illuminate\Database\Query\Builder|Config whereLastModifiedDate( $value )
 */
class Config extends BaseSystemModel
{
    use SingleRecordModel;

    protected $primaryKey = 'db_version';

    protected $table = 'system_config';

    protected $fillable = [ 'db_version', 'login_with_user_name', 'api_key', 'allow_guest_access', 'guest_role_id' ];

    public static function instance()
    {
        $models = static::all();
        $model = $models->first();

        return $model;
    }

    public static function boot()
    {
        parent::boot();

        static::saved(
            function ( Config $config )
            {
                if ( Cache::has( 'system_config' ) )
                {
                    Cache::forget( 'system_config' );
                }
            }
        );
    }
}