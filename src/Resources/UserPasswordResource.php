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
use DreamFactory\Rave\Exceptions\NotFoundException;
use DreamFactory\Rave\Exceptions\InternalServerErrorException;
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
        $oldPassword = $this->getPayloadData('old_password');
        $newPassword = $this->getPayloadData('new_password');

        if ( !empty( $oldPassword ) && \Auth::check() )
        {
            $user = \Auth::getUser();
            return static::changePassword( $user, $oldPassword, $newPassword );
        }

        $reset = $this->request->getParameterAsBool('reset');
        $login = $this->request->getParameterAsBool( 'login' );
        $email = $this->getPayloadData( 'email' );
        $code = $this->getPayloadData( 'code' );
        $answer = $this->getPayloadData( 'security_answer' );

        if ( true === $reset)
        {
            return static::passwordReset( $email );
        }

        if ( !empty( $code ) )
        {
            return static::changePasswordByCode( $email, $code, $newPassword, $login, true );
        }

        if ( !empty( $answer ) )
        {
            return static::changePasswordBySecurityAnswer( $email, $answer, $newPassword, $login, true );
        }

        return false;
    }

    protected static function changePassword( User $user, $old, $new )
    {
        // query with check for old password
        // then update with new password
        if ( empty( $old ) || empty( $new ) )
        {
            throw new BadRequestException( 'Both old and new password are required to change the password.' );
        }

        if ( null === $user )
        {
            // bad session
            throw new NotFoundException( "The user for the current session was not found in the system." );
        }

        try
        {
            // validate password
            $isValid = \Hash::check($old, $user->password);
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error validating old password.\n{$ex->getMessage()}" );
        }

        if ( !$isValid )
        {
            throw new BadRequestException( "The password supplied does not match." );
        }

        try
        {
            $user->password = bcrypt($new);
            $user->save();

            return array('success' => true);
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error processing password change.\n{$ex->getMessage()}" );
        }
    }

    protected static function passwordReset($email)
    {
        if ( empty( $email ) )
        {
            throw new BadRequestException( "Missing required email for password reset confirmation." );
        }

        /** @var User $_theUser */
        $user = User::whereEmail($email)->get();

        if ( null === $user )
        {
            // bad code
            throw new NotFoundException( "The supplied email was not found in the system." );
        }

        // if security question and answer provisioned, start with that
        $question = $user->security_question;
        if ( !empty( $question ) )
        {
            return array('security_question' => $question);
        }

        // otherwise, is email confirmation required?
        /** @var $_config Config */
        $_fields = 'password_email_service_id, password_email_template_id';
        if ( null === ( $_config = Config::model()->find( array('select' => $_fields) ) ) )
        {
            throw new InternalServerErrorException( 'Unable to load system configuration.' );
        }

        $_serviceId = $_config->password_email_service_id;
        if ( !empty( $_serviceId ) )
        {
            $_code = Hasher::generateUnique( $email, 32 );
            try
            {
                $_theUser->setAttribute( 'confirm_code', $_code );
                $_theUser->save();

                /** @var EmailSvc $_emailService */
                $_emailService = ServiceHandler::getServiceObject( $_serviceId );
                if ( !$_emailService )
                {
                    throw new \Exception( "Bad service identifier '$_serviceId'." );
                }

                $_data = array();
                $_template = $_config->password_email_template_id;
                if ( !empty( $_template ) )
                {
                    $_data['template_id'] = $_template;
                }
                else
                {
                    $_defaultPath = Platform::getLibraryTemplatePath( '/email/confirm_password_reset.json' );

                    if ( !file_exists( $_defaultPath ) )
                    {
                        throw new \Exception( "No default email template for password reset." );
                    }

                    $_data = file_get_contents( $_defaultPath );
                    $_data = json_decode( $_data, true );
                    if ( empty( $_data ) || !is_array( $_data ) )
                    {
                        throw new \Exception( "No data found in default email template for password reset." );
                    }
                }

                $_data['to'] = $email;
                $_userFields = array('first_name', 'last_name', 'display_name', 'confirm_code');
                $_data = array_merge( $_data, $_theUser->getAttributes( $_userFields ) );
                $_emailService->sendEmail( $_data );

                return array('success' => true);
            }
            catch ( \Exception $ex )
            {
                throw new InternalServerErrorException( "Error processing password reset.\n{$ex->getMessage()}", $ex->getCode() );
            }
        }

        throw new InternalServerErrorException(
            'No security question found or email confirmation available for this user. Please contact your administrator.'
        );
    }

    protected static function changePasswordByCode( $email, $code, $newPassword, $login = true, $returnExtras = false )
    {

    }

    protected static function changePasswordBySecurityAnswer( $email, $answer, $newPassword, $login = true, $returnExtras = false )
    {

    }

    protected static function sendPasswordResetConfirmationEmail(User $user)
    {

    }
}