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

namespace DreamFactory\Rave\Resources\System;

use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\UnauthorizedException;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Utility\Session as SessionUtil;
use Carbon\Carbon;

trait UserSessionTrait
{
    /**
     * Gets basic user session data.
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        return SessionUtil::getUserInfo();
    }

    /**
     * Authenticates valid user.
     *
     * @return array
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    protected function handlePOST()
    {
        return $this->handleLogin();
    }

    /**
     * Performs login.
     *
     * @param array $credentials
     *
     * @return array
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \Exception
     */
    protected function handleLogin( array $credentials = [ ] )
    {
        $email = ArrayUtils::get( $credentials, 'email' );
        $password = ArrayUtils::get( $credentials, 'password' );

        if ( empty( $email ) )
        {
            ArrayUtils::set( $credentials, 'email', $this->getPayloadData( 'email' ) );
        }

        if ( empty( $password ) )
        {
            ArrayUtils::set( $credentials, 'password', $this->getPayloadData( 'password' ) );
        }

        ArrayUtils::set( $credentials, 'is_active', 1 );

        //if user management not available then only system admins can login.
        if ( !class_exists( '\DreamFactory\Rave\User\Resources\System\User' ) )
        {
            ArrayUtils::set( $credentials, 'is_sys_admin', 1 );
        }

        $rememberMe = boolval( $this->getPayloadData( 'remember_me' ) );

        if ( \Auth::attempt( $credentials, $rememberMe ) )
        {
            $user = \Auth::getUser();
            $user->update( [ 'last_login_date' => Carbon::now()->toDateTimeString() ] );
            SessionUtil::setUserInfo( $user );

            return SessionUtil::getUserInfo();
        }
        else
        {
            throw new UnauthorizedException( 'Invalid user name and password combination.' );
        }
    }

    /**
     * Logs out user
     *
     * @return array
     */
    protected function handleDELETE()
    {
        \Auth::logout();

        return [ 'success' => true ];
    }

    /**
     * Handles PATCH action.
     *
     * @return bool
     */
    protected function handlePATCH()
    {
        return false;
    }
}