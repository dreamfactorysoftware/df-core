<?php
/**
 * This file is part of the DreamFactory(tm) Core
 *
 * DreamFactory(tm) Core <http://github.com/dreamfactorysoftware/df-core>
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
namespace DreamFactory\Core\Models;

/**
 * UserAppRole
 *
 * @property integer $user_id
 * @property integer $app_id
 * @property integer $role_id
 * @method static \Illuminate\Database\Query\Builder|User whereUserId( $value )
 * @method static \Illuminate\Database\Query\Builder|User whereAppId( $value )
 * @method static \Illuminate\Database\Query\Builder|User whereRoleId( $value )
 */
class UserAppRole extends BaseModel
{
    protected $table = 'user_to_app_to_role';

    protected $fillable = ['user_id', 'app_id', 'role_id'];

    public $timestamps = false;
}