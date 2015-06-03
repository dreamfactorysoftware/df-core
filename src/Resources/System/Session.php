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
use DreamFactory\Rave\Resources\UserSessionResource;
use DreamFactory\Rave\Exceptions\NotFoundException;

class Session extends UserSessionResource
{
    /**
     * {@inheritdoc}
     */
    protected function handleGET()
    {
        $user = \Auth::user();

        if ( empty( $user ) )
        {
            throw new NotFoundException( 'No user session found.' );
        }

        if ( false === Scalar::boolval( $user->is_sys_admin ) )
        {
            throw new UnauthorizedException( 'You are not authorized to perform this action.' );
        }

        return parent::handleGET();
    }

    /**
     * {@inheritdoc}
     */
    protected function handlePOST()
    {
        $credentials = [
            'email'        => $this->getPayloadData( 'email' ),
            'password'     => $this->getPayloadData( 'password' ),
            'is_sys_admin' => 1
        ];

        return $this->handleLogin( $credentials );
    }
}