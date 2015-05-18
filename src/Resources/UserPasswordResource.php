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

use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Exceptions\BadRequestException;
use DreamFactory\Rave\Models\User;

class UserPasswordResource extends BaseRestResource
{
    const RESOURCE_NAME = 'password';

    /**
     * @param array $settings
     */
    public function __construct( $settings = [ ] )
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::POST,
            Verbs::MERGE => Verbs::POST,
            Verbs::PATCH => Verbs::POST
        ];
        ArrayUtils::set( $settings, "verbAliases", $verbAliases );

        parent::__construct( $settings );
    }

    protected function handleGET()
    {
        return false;
    }

    protected function handlePUT()
    {
        return false;
    }

    protected function handlePATCH()
    {
        return false;
    }

    /**
     * Resets user password.
     *
     * @return array|bool
     * @throws BadRequestException
     * @throws \Exception
     */
    protected function handlePOST()
    {
//        $oldPassword = $this->getPayloadData('old_password');
//        $newPassword = $this->getPayloadData('new_password');
//
//        if ( !empty( $oldPassword ) && \Auth::check() )
//        {
//            $user = \Auth::getUser();
//
//            return $this->changePassword( $user, $oldPassword, $newPassword );
//        }
//
//        $reset = $this->getQueryBool( 'reset' );
//        $login = $this->getQueryBool( 'login' );
//        $email = $this->getPayloadData( 'email' );
//        $code = $this->getPayloadData( 'code' );
//        $answer = $this->getPayloadData( 'security_answer' );
//
//        if ( Option::getBool( $this->_requestPayload, 'reset', FilterInput::request( 'reset' ) ) )
//        {
//            return $this->passwordReset( $_email );
//        }
//
//        if ( !empty( $_code ) )
//        {
//            return $this->changePasswordByCode( $_email, $_code, $_new, $_login, true );
//        }
//
//        if ( !empty( $_answer ) )
//        {
//            return $this->changePasswordBySecurityAnswer( $_email, $_answer, $_new, $_login, true );
//        }

        return false;

//        $reset = $this->getQueryBool( 'reset' );
//        $login = $this->getQueryBool( 'login' );
//
//        $payload = $this->getPayloadData();
//
//        $oldPassword = ArrayUtils::get( $payload, 'old_password' );
//        $newPassword = ArrayUtils::get( $payload, 'new_password' );
//        $email = ArrayUtils::get( $payload, 'email' );
//        $code = ArrayUtils::get( $payload, 'code' );
//        $answer = ArrayUtils::get( $payload, 'security_answer' );
//
//        if ( $reset && $oldPassword && \Auth::check() )
//        {
//            /** @var User $user */
//            $user = \Auth::getUser();
//            $userPassword = $user->getAuthPassword();
//
//            if ( \Hash::check( $oldPassword, $userPassword ) )
//            {
//                $user->update( [ 'password' => bcrypt( $newPassword ) ] );
//
//                return [ 'success' => true ];
//            }
//            else
//            {
//                throw new BadRequestException( 'Error validating old password.' );
//            }
//        }
//
//        return false;
    }

    protected function changePassword( User $user, $oldPassword, $newPassword )
    {

    }
}