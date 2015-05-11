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
use Laravel\Socialite\Contracts\User as OAuthUserContract;
use DreamFactory\DSP\OAuth\Services\BaseOAuthService;
use DreamFactory\DSP\ADLdap\Contracts\User as LdapUserContract;
use DreamFactory\DSP\ADLdap\Services\LDAP as LdapService;

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
        'email',
        'password',
        'is_active',
        'phone',
        'security_question',
        'security_answer',
        'adldap',
        'oauth_provider',
        'last_login_date'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'password', 'remember_token' ];

    protected static $tableToModelMap = [
        'user_to_app_role' => 'DreamFactory\Rave\Models\UserAppRole',
        'user_lookup' => 'DreamFactory\Rave\Models\UserLookup'
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

    /**
     * If does not exists, creates a shadow OAuth user using user info provided
     * by the OAuth service provider and assigns default role to this user
     * for all apps in the system. If user already exists then updates user's
     * role for all apps and returns it.
     *
     * @param OAuthUserContract $OAuthUser
     * @param BaseOAuthService  $service
     *
     * @return User
     * @throws \Exception
     */
    public static function createShadowOAuthUser( OAuthUserContract $OAuthUser, BaseOAuthService $service )
    {
        $fullName = $OAuthUser->getName();
        @list( $firstName, $lastName ) = explode( ' ', $fullName );

        $email = $OAuthUser->getEmail();
        $serviceName = $service->getName();
        $providerName = $service->getProviderName();
        $accessToken = $OAuthUser->token;

        if(empty($email))
        {
            $email = $OAuthUser->getId().'+'.$serviceName.'@'.$serviceName.'.com';
        }
        else
        {
            list( $emailId, $domain ) = explode( '@', $email );
            $email = $emailId . '+' . $serviceName . '@' . $domain;
        }

        $user = static::whereEmail($email)->first();

        if(empty($user))
        {
            $data = [
                'name'           => $fullName,
                'first_name'     => $firstName,
                'last_name'      => $lastName,
                'email'          => $email,
                'is_active'      => 1,
                'oauth_provider' => $providerName,
                'password'       => $accessToken
            ];

            $user = static::create( $data );

        }

        $defaultRole = $service->getDefaultRole();

        static::applyDefaultUserAppRole($user, $defaultRole);

        return $user;
    }

    /**
     * If does not exists, creates a shadow LDap user using user info provided
     * by the Ldap service provider and assigns default role to this user
     * for all apps in the system. If user already exists then updates user's
     * role for all apps and returns it.
     *
     * @param LdapUserContract $ldapUser
     * @param LdapService      $service
     *
     * @return User
     * @throws \Exception
     */
    public static function createShadowLdapUser(LdapUserContract $ldapUser, LdapService $service)
    {
        $email = $ldapUser->getEmail();
        $serviceName = $service->getName();

        if(empty($email))
        {
            $uid = $ldapUser->getUid();
            if(empty($uid))
            {
                $uid = str_replace(' ', '', $ldapUser->getName());
            }
            $domain = $ldapUser->getDomain();
            $email = $uid.'+'.$serviceName.'@'.$domain;
        }
        else
        {
            list($emailId, $domain) = explode('@', $email);
            $email = $emailId.'+'.$serviceName.'@'.$domain;
        }

        $user = static::whereEmail($email)->first();

        if(empty($user))
        {
            $data = [
                'name'           => $ldapUser->getName(),
                'first_name'     => $ldapUser->getFirstName(),
                'last_name'      => $ldapUser->getLastName(),
                'email'          => $email,
                'is_active'      => 1,
                'adldap'         => $service->getProviderName(),
                'password'       => $ldapUser->getPassword()
            ];

            $user = static::create( $data );
        }

        $defaultRole = $service->getDefaultRole();

        static::applyDefaultUserAppRole($user, $defaultRole);

        return $user;
    }

    /**
     * Assigns a role to a user for all apps in the system.
     *
     * @param $user
     * @param $defaultRole
     *
     * @return bool
     * @throws \Exception
     */
    protected static function applyDefaultUserAppRole($user, $defaultRole)
    {
        $apps = App::all();

        if(count($apps) === 0)
        {
            return false;
        }

        foreach($apps as $app)
        {
            if(!UserAppRole::whereUserId($user->id)->whereAppId($app->id)->exists())
            {
                $userAppRoleData = [
                    'user_id' => $user->id,
                    'app_id'  => $app->id,
                    'role_id' => $defaultRole
                ];

                UserAppRole::create( $userAppRoleData );
            }
        }

        return true;
    }

    public function getEmailAttribute($value)
    {
        if(false !== strpos($value, '+'))
        {
            list($emailId, $domain) = explode('@', $value);
            list($emailId, $provider) = explode('+', $emailId);

            $value = $emailId.'@'.$domain;
        }

        return $value;
    }
}