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

use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Rave\Exceptions\UnauthorizedException;
use DreamFactory\Rave\Resources\UserPasswordResource;
use DreamFactory\Rave\Models\User;
use DreamFactory\Rave\Exceptions\NotFoundException;
use Mail;

class Password extends UserPasswordResource
{
    /**
     * {@inheritdoc}
     */
    protected static function sendPasswordResetEmail( User $user )
    {
        $email = $user->email;
        $code = $user->confirm_code;

        Mail::send(
            'emails.password',
            [ 'token' => $code ],
            function ( $m ) use ( $email )
            {
                $m->to( $email )->subject( 'Your password reset link' );
            }
        );

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected static function isAllowed( User $user )
    {
        if ( null === $user )
        {
            throw new NotFoundException( "User not found in the system." );
        }

        if ( false === Scalar::boolval( $user->is_sys_admin ) )
        {
            throw new UnauthorizedException( 'You are not authorized to reset/change password for the account ' . $user->email );
        }

        return true;
    }
}