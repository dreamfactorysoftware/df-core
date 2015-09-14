<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Utility\Session;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\User;
use Mail;

class UserPasswordResource extends BaseRestResource
{
    const RESOURCE_NAME = 'password';

    /**
     * @param array $settings
     */
    public function __construct($settings = [])
    {
        $verbAliases = [
            Verbs::PUT   => Verbs::POST,
            Verbs::MERGE => Verbs::POST,
            Verbs::PATCH => Verbs::POST
        ];
        ArrayUtils::set($settings, "verbAliases", $verbAliases);

        parent::__construct($settings);
    }

    /**
     * {@inheritdoc}
     */
    protected function handleGET()
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

        if (!empty($oldPassword) && Session::isAuthenticated()) {
            $user = Session::user();

            return static::changePassword($user, $oldPassword, $newPassword);
        }

        $login = $this->request->getParameterAsBool('login');
        $email = $this->getPayloadData('email');
        $code = $this->getPayloadData('code');
        $answer = $this->getPayloadData('security_answer');

        if ($this->request->getParameterAsBool('reset')) {
            return static::passwordReset($email);
        }

        if (!empty($code)) {
            return static::changePasswordByCode($email, $code, $newPassword, $login);
        }

        if (!empty($answer)) {
            return static::changePasswordBySecurityAnswer($email, $answer, $newPassword, $login);
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
    protected static function changePassword(User $user, $old, $new)
    {
        static::isAllowed($user);

        // query with check for old password
        // then update with new password
        if (empty($old) || empty($new)) {
            throw new BadRequestException('Both old and new password are required to change the password.');
        }

        if (null === $user) {
            // bad session
            throw new NotFoundException("The user for the current session was not found in the system.");
        }

        try {
            // validate password
            $isValid = \Hash::check($old, $user->password);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Error validating old password.\n{$ex->getMessage()}");
        }

        if (!$isValid) {
            throw new BadRequestException("The password supplied does not match.");
        }

        try {
            $user->password = $new;
            $user->save();

            return array('success' => true);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Error processing password change.\n{$ex->getMessage()}");
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
    protected static function passwordReset($email)
    {
        if (empty($email)) {
            throw new BadRequestException("Missing required email for password reset confirmation.");
        }

        /** @var User $user */
        $user = User::whereEmail($email)->first();

        if (null === $user) {
            // bad code
            throw new NotFoundException("The supplied email was not found in the system.");
        }

        static::isAllowed($user);

        // if security question and answer provisioned, start with that
        $question = $user->security_question;
        if (!empty($question)) {
            return array('security_question' => $question);
        }

        // otherwise, is email confirmation required?
        $code = \Hash::make($email);
        $user->confirm_code = base64_encode($code);
        $user->save();

        $sent = static::sendPasswordResetEmail($user);

        if (true === $sent) {
            return array('success' => true);
        } else {
            throw new InternalServerErrorException(
                'No security question found or email confirmation available for this user. Please contact your administrator.'
            );
        }
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
    public static function changePasswordByCode($email, $code, $newPassword, $login = true)
    {
        if (empty($email)) {
            throw new BadRequestException("Missing required email for password reset confirmation.");
        }

        if (empty($newPassword)) {
            throw new BadRequestException("Missing new password for reset.");
        }

        if (empty($code) || 'y' == $code) {
            throw new BadRequestException("Invalid confirmation code.");
        }

        /** @var User $user */
        $user = User::whereEmail($email)->whereConfirmCode($code)->first();

        if (null === $user) {
            // bad code
            throw new NotFoundException("The supplied email and/or confirmation code were not found in the system.");
        }

        static::isAllowed($user);

        try {
            $user->confirm_code = 'y';
            $user->password = $newPassword;
            $user->save();
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Error processing password reset.\n{$ex->getMessage()}");
        }

        if ($login) {
            static::userLogin($email, $newPassword);

            return ['success' => true, 'session_token' => Session::getSessionToken()];
        }

        return ['success' => true];
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
    protected static function changePasswordBySecurityAnswer($email, $answer, $newPassword, $login = true)
    {
        if (empty($email)) {
            throw new BadRequestException("Missing required email for password reset confirmation.");
        }

        if (empty($newPassword)) {
            throw new BadRequestException("Missing new password for reset.");
        }

        if (empty($answer)) {
            throw new BadRequestException("Missing security answer.");
        }

        /** @var User $user */
        $user = User::whereEmail($email)->first();

        if (null === $user) {
            // bad code
            throw new NotFoundException("The supplied email and confirmation code were not found in the system.");
        }

        static::isAllowed($user);

        try {
            // validate answer
            $isValid = \Hash::check($answer, $user->security_answer);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Error validating security answer.\n{$ex->getMessage()}");
        }

        if (!$isValid) {
            throw new BadRequestException("The answer supplied does not match.");
        }

        try {
            $user->password = $newPassword;
            $user->save();
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Error processing password change.\n{$ex->getMessage()}");
        }

        if ($login) {
            static::userLogin($email, $newPassword);

            return ['success' => true, 'session_token' => Session::getSessionToken()];
        }

        return ['success' => true];
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
    protected static function userLogin($email, $password)
    {
        try {
            $credentials = ['email' => $email, 'password' => $password];
            Session::authenticate($credentials);
        } catch (\Exception $ex) {
            throw new InternalServerErrorException("Password set, but failed to login.\n{$ex->getMessage()}");
        }

        return true;
    }

    /**
     * Sends the user an email with password reset link.
     *
     * @param User $user
     *
     * @return bool
     * @throws InternalServerErrorException
     */
    protected static function sendPasswordResetEmail(User $user)
    {
        $email = $user->email;
        $code = $user->confirm_code;
        $name = $user->first_name;

        Mail::send(
            'emails.password',
            [
                'contentHeader' => 'Password Reset',
                'firstName'          => $name,
                'code'          => $code,
                'link'          => url(\Config::get('df.confirm_reset_url')) . '?code=' . $code
            ],
            function ($m) use ($email){
                $m->to($email)->subject('[DF] Password Reset');
            }
        );

        return true;
    }

    /**
     * Checks to see if the user is allowed to reset/change password.
     *
     * @param User $user
     *
     * @return bool
     * @throws NotFoundException
     */
    protected static function isAllowed(User $user)
    {
        if (null === $user) {
            throw new NotFoundException("User not found in the system.");
        }

        return true;
    }
}