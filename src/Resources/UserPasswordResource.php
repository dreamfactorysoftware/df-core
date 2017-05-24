<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Components\Registrar;
use DreamFactory\Core\Contracts\EmailServiceInterface;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\ServiceUnavailableException;
use DreamFactory\Core\Models\EmailTemplate;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Session;
use ServiceManager;

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
            Verbs::PATCH => Verbs::POST
        ];
        $settings["verbAliases"] = $verbAliases;

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
        $username = $this->getPayloadData('username');
        $code = $this->getPayloadData('code');
        $answer = $this->getPayloadData('security_answer');
        $loginAttribute = strtolower(config('df.login_attribute', 'email'));

        if ($this->request->getParameterAsBool('reset')) {
            if ($loginAttribute === 'username') {
                return $this->passwordResetWithUsername($username);
            }

            return $this->passwordReset($email);
        }

        if (!empty($code)) {
            if ($loginAttribute === 'username') {
                return static::changePasswordByCodeWithUsername($username, $code, $newPassword, $login);
            }

            return static::changePasswordByCode($email, $code, $newPassword, $login);
        }

        if (!empty($answer)) {
            if ($loginAttribute === 'username') {
                return static::changePasswordBySecurityAnswerWithUsername($username, $answer, $newPassword, $login);
            }

            return static::changePasswordBySecurityAnswer($email, $answer, $newPassword, $login);
        }

        return false;
    }

    /**
     * @param string $username
     *
     * @return array
     */
    protected function passwordResetWithUsername($username)
    {
        return $this->passwordReset(static::getEmailByUsername($username));
    }

    /**
     * @param string  $username
     * @param string  $code
     * @param string  $newPassword
     * @param boolean $login
     *
     * @return array
     */
    public static function changePasswordByCodeWithUsername($username, $code, $newPassword, $login)
    {
        return static::changePasswordByCode(static::getEmailByUsername($username), $code, $newPassword, $login);
    }

    /**
     * @param string  $username
     * @param string  $answer
     * @param string  $newPassword
     * @param boolean $login
     *
     * @return array
     */
    protected static function changePasswordBySecurityAnswerWithUsername($username, $answer, $newPassword, $login)
    {
        return static::changePasswordBySecurityAnswer(
            static::getEmailByUsername($username),
            $answer,
            $newPassword,
            $login
        );
    }

    /**
     * @param string $username
     *
     * @return string
     * @throws \DreamFactory\Core\Exceptions\BadRequestException
     * @throws \DreamFactory\Core\Exceptions\NotFoundException
     */
    protected static function getEmailByUsername($username)
    {
        if (empty($username)) {
            throw new BadRequestException("Missing required username.");
        }

        /** @var User $user */
        $user = User::whereUsername($username)->get(['email'])->first();

        if (empty($user)) {
            throw new NotFoundException('The supplied username was not found in the system.');
        }

        return $user->email;
    }

    /**
     * {@inheritdoc}
     */
    public static function getApiDocInfo($service, array $resource = [])
    {
        $serviceName = strtolower($service);
        $capitalized = camelize($service);
        $class = trim(strrchr(static::class, '\\'), '\\');
        $resourceName = strtolower(array_get($resource, 'name', $class));
        $path = '/' . $serviceName . '/' . $resourceName;
        $apis = [
            $path => [
                'post' => [
                    'tags'        => [$serviceName],
                    'summary'     => 'change' .
                        $capitalized .
                        'Password() - Change or reset the current user\'s password.',
                    'operationId' => 'change' . $capitalized . 'Password',
                    'parameters'  => [
                        [
                            'name'        => 'body',
                            'description' => 'Data containing name-value pairs for password change.',
                            'schema'      => ['$ref' => '#/definitions/PasswordRequest'],
                            'in'          => 'body',
                            'required'    => true,
                        ],
                        [
                            'name'        => 'reset',
                            'description' => 'Set to true to perform password reset.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'required'    => false,
                        ],
                        [
                            'name'        => 'login',
                            'description' => 'Login and create a session upon successful password reset.',
                            'type'        => 'boolean',
                            'in'          => 'query',
                            'required'    => false,
                        ],
                    ],
                    'responses'   => [
                        '200'     => [
                            'description' => 'Success',
                            'schema'      => ['$ref' => '#/definitions/PasswordResponse']
                        ],
                        'default' => [
                            'description' => 'Error',
                            'schema'      => ['$ref' => '#/definitions/Error']
                        ]
                    ],
                    'description' =>
                        'A valid current session along with old and new password are required to change ' .
                        'the password directly posting \'old_password\' and \'new_password\'. <br/>' .
                        'To request password reset, post \'email\' and set \'reset\' to true. <br/>' .
                        'To reset the password from an email confirmation, post \'email\', \'code\', and \'new_password\'. <br/>' .
                        'To reset the password from a security question, post \'email\', \'security_answer\', and \'new_password\'.',
                ],
            ],
        ];

        $models = [
            'PasswordRequest'  => [
                'type'       => 'object',
                'properties' => [
                    'old_password' => [
                        'type'        => 'string',
                        'description' => 'Old password to validate change during a session.',
                    ],
                    'new_password' => [
                        'type'        => 'string',
                        'description' => 'New password to be set.',
                    ],
                    'email'        => [
                        'type'        => 'string',
                        'description' => 'User\'s email to be used with code to validate email confirmation.',
                    ],
                    'code'         => [
                        'type'        => 'string',
                        'description' => 'Code required with new_password when using email confirmation.',
                    ],
                ],
            ],
            'PasswordResponse' => [
                'type'       => 'object',
                'properties' => [
                    'security_question' => [
                        'type'        => 'string',
                        'description' => 'User\'s security question, returned on reset request when no email confirmation required.',
                    ],
                    'success'           => [
                        'type'        => 'boolean',
                        'description' => 'True if password updated or reset request granted via email confirmation.',
                    ],
                ],
            ],
        ];

        return ['paths' => $apis, 'definitions' => $models];
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
    protected function passwordReset($email)
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
        $user->confirm_code = Registrar::generateConfirmationCode(\Config::get('df.confirm_code_length', 32));
        $user->save();

        $sent = $this->sendPasswordResetEmail($user);

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
        } elseif ($user->isConfirmationExpired()) {
            throw new BadRequestException("Confirmation code expired.");
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
    protected function sendPasswordResetEmail(User $user)
    {
        $email = $user->email;

        /** @var \DreamFactory\Core\User\Services\User $parent */
        $parent = $this->getParent();

        if (!empty($parent->passwordEmailServiceId)) {
            try {
                /** @var EmailServiceInterface $emailService */
                $emailService = ServiceManager::getServiceById($parent->passwordEmailServiceId);

                if (empty($emailService)) {
                    throw new ServiceUnavailableException("Bad email service identifier.");
                }

                $data = [];
                if (!empty($parent->passwordEmailTemplateId)) {
                    // find template in system db
                    $template = EmailTemplate::whereId($parent->passwordEmailTemplateId)->first();
                    if (empty($template)) {
                        throw new NotFoundException("Email Template id '{$parent->passwordEmailTemplateId}' not found");
                    }

                    $data = $template->toArray();
                }

                if (empty($data) || !is_array($data)) {
                    throw new ServiceUnavailableException("No data found in default email template for password reset.");
                }

                $data['to'] = $email;
                $data['content_header'] = 'Password Reset';
                $data['first_name'] = $user->first_name;
                $data['last_name'] = $user->last_name;
                $data['name'] = $user->name;
                $data['phone'] = $user->phone;
                $data['email'] = $user->email;
                $data['link'] = url(\Config::get('df.confirm_reset_url')) .
                    '?code=' . $user->confirm_code .
                    '&email=' . $email .
                    '&username=' . $user->username;
                $data['confirm_code'] = $user->confirm_code;

                $bodyHtml = array_get($data, 'body_html');
                $bodyText = array_get($data, 'body_text');

                if (empty($bodyText) && !empty($bodyHtml)) {
                    $bodyText = strip_tags($bodyHtml);
                    $bodyText = preg_replace('/ +/', ' ', $bodyText);
                }

                $emailService->sendEmail($data, $bodyText, $bodyHtml);

                return true;
            } catch (\Exception $ex) {
                throw new InternalServerErrorException("Error processing password reset.\n{$ex->getMessage()}");
            }
        }

        return false;
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