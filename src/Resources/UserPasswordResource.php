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
use DreamFactory\Rave\Exceptions\ServiceUnavailableException;
use DreamFactory\Rave\Models\Config as SystemConfig;
use DreamFactory\Rave\Models\User;
use DreamFactory\Rave\Utility\ServiceHandler;
use DreamFactory\Rave\Services\Email\BaseService as EmailService;

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

    /**
     * {@inheritdoc}
     */
    protected function handleGET()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function handlePUT()
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
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
        $oldPassword = $this->getPayloadData( 'old_password' );
        $newPassword = $this->getPayloadData( 'new_password' );

        if ( !empty( $oldPassword ) && \Auth::check() )
        {
            $user = \Auth::user();

            return static::changePassword( $user, $oldPassword, $newPassword );
        }

        $reset = $this->request->getParameterAsBool( 'reset' );
        $login = $this->request->getParameterAsBool( 'login' );
        $email = $this->getPayloadData( 'email' );
        $code = $this->getPayloadData( 'code' );
        $answer = $this->getPayloadData( 'security_answer' );

        if ( true === $reset )
        {
            return static::passwordReset( $email );
        }

        if ( !empty( $code ) )
        {
            return static::changePasswordByCode( $email, $code, $newPassword, $login );
        }

        if ( !empty( $answer ) )
        {
            return static::changePasswordBySecurityAnswer( $email, $answer, $newPassword, $login );
        }

        return false;
    }

    /**
     * Changes password.
     *
     * @param User   $user
     * @param string $old
     * @param string $new
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
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
            $isValid = \Hash::check( $old, $user->password );
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
            $user->password = bcrypt( $new );
            $user->save();

            return array( 'success' => true );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error processing password change.\n{$ex->getMessage()}" );
        }
    }

    /**
     * Resets password.
     *
     * @param $email
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    protected static function passwordReset( $email )
    {
        if ( empty( $email ) )
        {
            throw new BadRequestException( "Missing required email for password reset confirmation." );
        }

        /** @var User $user */
        $user = User::whereEmail( $email )->first();

        if ( null === $user )
        {
            // bad code
            throw new NotFoundException( "The supplied email was not found in the system." );
        }

        // if security question and answer provisioned, start with that
        $question = $user->security_question;
        if ( !empty( $question ) )
        {
            return array( 'security_question' => $question );
        }

        // otherwise, is email confirmation required?
        /** @var $config Config */
        $config = SystemConfig::instance();

        if ( empty( $config ) )
        {
            throw new InternalServerErrorException( 'Unable to load system configuration.' );
        }

        $emailServiceId = $config->password_email_service_id;
        if ( !empty( $emailServiceId ) )
        {
            $code = \Hash::make( $email );
            try
            {
                $user->confirm_code = $code;
                $user->save();

                /** @var EmailService $emailService */
                $emailService = ServiceHandler::getServiceById( $emailServiceId );

                if ( empty( $emailService ) )
                {
                    throw new ServiceUnavailableException( "Bad service identifier '$emailServiceId'." );
                }

                $data = array();
                $templateId = $config->password_email_template_id;

                if ( !empty( $templateId ) )
                {
                    $data = $emailService::getTemplateDataById( $templateId );
                }

                if ( empty( $data ) || !is_array( $data ) )
                {
                    throw new ServiceUnavailableException( "No data found in default email template for password reset." );
                }

                ArrayUtils::set( $data, 'to', $email );
                ArrayUtils::set( $data, 'first_name', $user->first_name );
                ArrayUtils::set( $data, 'last_name', $user->last_name );
                ArrayUtils::set( $data, 'name', $user->name );
                ArrayUtils::set( $data, 'confirm_code', $user->confirm_code );

                $emailService->sendEmail( $data, ArrayUtils::get( $data, 'body_text' ), ArrayUtils::get( $data, 'body_html' ) );

                return array( 'success' => true );
            }
            catch ( \Exception $ex )
            {
                throw new InternalServerErrorException( "Error processing password reset.\n{$ex->getMessage()}" );
            }
        }

        throw new InternalServerErrorException(
            'No security question found or email confirmation available for this user. Please contact your administrator.'
        );
    }

    /**
     * Changes password by confirmation code.
     *
     * @param      $email
     * @param      $code
     * @param      $newPassword
     * @param bool $login
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    protected static function changePasswordByCode( $email, $code, $newPassword, $login = true )
    {
        if ( empty( $email ) )
        {
            throw new BadRequestException( "Missing required email for password reset confirmation." );
        }

        if ( empty( $newPassword ) )
        {
            throw new BadRequestException( "Missing new password for reset." );
        }

        if ( empty( $code ) || 'y' == $code )
        {
            throw new BadRequestException( "Invalid confirmation code." );
        }

        /** @var User $user */
        $user = User::whereEmail( $email )->whereConfirmCode( $code )->first();

        if ( null === $user )
        {
            // bad code
            throw new NotFoundException( "The supplied email and/or confirmation code were not found in the system." );
        }

        try
        {
            $user->confirm_code = 'y';
            $user->password = bcrypt( $newPassword );
            $user->save();
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error processing password reset.\n{$ex->getMessage()}" );
        }

        if ( $login )
        {
            static::userLogin( $email, $newPassword );
        }

        return array( 'success' => true );
    }

    /**
     * Changes password by security answer.
     *
     * @param      $email
     * @param      $answer
     * @param      $newPassword
     * @param bool $login
     *
     * @return array
     * @throws BadRequestException
     * @throws InternalServerErrorException
     * @throws NotFoundException
     */
    protected static function changePasswordBySecurityAnswer( $email, $answer, $newPassword, $login = true )
    {
        if ( empty( $email ) )
        {
            throw new BadRequestException( "Missing required email for password reset confirmation." );
        }

        if ( empty( $newPassword ) )
        {
            throw new BadRequestException( "Missing new password for reset." );
        }

        if ( empty( $answer ) )
        {
            throw new BadRequestException( "Missing security answer." );
        }

        /** @var User $user */
        $user = User::whereEmail( $email )->first();

        if ( null === $user )
        {
            // bad code
            throw new NotFoundException( "The supplied email and confirmation code were not found in the system." );
        }

        try
        {
            // validate answer
            $isValid = \Hash::check( $answer, $user->security_answer );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error validating security answer.\n{$ex->getMessage()}" );
        }

        if ( !$isValid )
        {
            throw new BadRequestException( "The answer supplied does not match." );
        }

        try
        {
            $user->password = bcrypt( $newPassword );
            $user->save();
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Error processing password change.\n{$ex->getMessage()}" );
        }

        if ( $login )
        {
            static::userLogin( $email, $newPassword );
        }

        return array( 'success' => true );
    }

    /**
     * Logs user in.
     *
     * @param $email
     * @param $password
     *
     * @return bool
     * @throws InternalServerErrorException
     */
    protected static function userLogin( $email, $password )
    {
        try
        {
            $credentials = [ 'email' => $email, 'password' => $password ];
            \Auth::attempt( $credentials );
        }
        catch ( \Exception $ex )
        {
            throw new InternalServerErrorException( "Password set, but failed to create a session.\n{$ex->getMessage()}" );
        }

        return true;
    }
}