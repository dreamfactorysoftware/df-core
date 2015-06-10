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

namespace DreamFactory\Rave\Resources;

use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\UnauthorizedException;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Rave\Utility\Session;
use Carbon\Carbon;

class UserSessionResource extends BaseRestResource
{
    const RESOURCE_NAME = 'session';

    /**
     * Gets basic user session data.
     *
     * @return array
     * @throws NotFoundException
     */
    protected function handleGET()
    {
        return Session::getPublicInfo();
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
        $credentials = [
            'email'        => $this->getPayloadData( 'email' ),
            'password'     => $this->getPayloadData( 'password' )
        ];

        return $this->handleLogin( $credentials, Scalar::boolval( $this->getPayloadData( 'remember_me' ) ) );
    }

    /**
     * Logs out user
     *
     * @return array
     */
    protected function handleDELETE()
    {
        \Auth::logout();

        //Clear everything in session.
        Session::flush();

        return [ 'success' => true ];
    }

    /**
     * Performs login.
     *
     * @param array $credentials
     * @param bool  $remember
     *
     * @return array
     * @throws BadRequestException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws \Exception
     */
    protected function handleLogin( array $credentials = [ ], $remember = false )
    {
        $email = ArrayUtils::get( $credentials, 'email' );
        if ( empty( $email ) )
        {
            throw new BadRequestException( 'Login request is missing required email.' );
        }

        $password = ArrayUtils::get( $credentials, 'password' );
        if ( empty( $password ) )
        {
            throw new BadRequestException( 'Login request is missing required password.' );
        }

        $credentials['is_active'] = true;

        // if user management not available then only system admins can login.
        if ( !class_exists( '\DreamFactory\Rave\User\Resources\System\User' ) )
        {
            $credentials['is_sys_admin'] = true;
        }

        if ( \Auth::attempt( $credentials, $remember ) )
        {
            $user = \Auth::user();
            $user->update( [ 'last_login_date' => Carbon::now()->toDateTimeString() ] );
            Session::setUserInfo( $user );

            return Session::getPublicInfo();
        }
        else
        {
            throw new BadRequestException( 'Invalid credentials supplied.' );
        }
    }
}