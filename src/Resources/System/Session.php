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

namespace DreamFactory\Core\Resources\System;

use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Resources\UserSessionResource;
use DreamFactory\Core\Exceptions\NotFoundException;

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
            'is_sys_admin' => true
        ];

        return $this->handleLogin( $credentials, Scalar::boolval( $this->getPayloadData( 'remember_me' ) ) );
    }
}