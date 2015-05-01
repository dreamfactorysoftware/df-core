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

use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends BaseSystemModel implements AuthenticatableContract, CanResetPasswordContract
{

    use Authenticatable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'last_login_date',
        'email',
        'password',
        'is_sys_admin',
        'is_active',
        'phone',
        'security_question',
        'security_answer',
        'confirm_code'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'password', 'remember_token' ];

    protected static $relatedModels = [
        'user_to_app_role' => 'DreamFactory\Rave\Models\UserAppRole'
    ];

    public static function seed()
    {
        $seeded = false;

        if ( !static::whereId( 1 )->exists() )
        {
            static::create(
                [
                    'id'           => 1,
                    'name'         => 'Rave Admin',
                    'email'        => 'admin@rave.' . gethostname() . '.com',
                    'password'     => bcrypt( 'rave_user' ),
                    'is_sys_admin' => 1,
                    'is_active'    => 1
                ]
            );
            $seeded = true;
        }

        return $seeded;
    }
}