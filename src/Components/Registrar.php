<?php

namespace DreamFactory\Core\Components;

use DreamFactory\Core\Contracts\EmailServiceInterface;
use DreamFactory\Core\Exceptions\ForbiddenException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Models\EmailTemplate;
use DreamFactory\Core\Models\User;
use Validator;
use ServiceManager;

class Registrar
{
    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validator(array $data)
    {
        $validationRules = [
            'name'       => 'required|max:255',
            'first_name' => 'required',
            'last_name'  => 'required',
            'email'      => 'required|email|max:255|unique:user',
            'username'   => 'min:6|unique:user,username|regex:/^\S*$/u|nullable'
        ];

        $loginAttribute = strtolower(config('df.login_attribute', 'email'));
        if ($loginAttribute === 'username') {
            $validationRules['username'] = str_replace('|nullable', '|required', $validationRules['username']);
        }

        /** @var \DreamFactory\Core\User\Services\User $userService */
        $userService = ServiceManager::getService('user');
        if (empty($userService->openRegEmailServiceId)) {
            $validationRules['password'] = 'required|confirmed|min:16';
        }

        return Validator::make($data, $validationRules);
    }

    /**
     * Creates a non-admin user.
     *
     * @param array   $data
     * @param integer $serviceId
     *
     * @return \DreamFactory\Core\Models\User
     * @throws \DreamFactory\Core\Exceptions\ForbiddenException
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     * @throws \Exception
     */
    public function create(array $data, $serviceId = null)
    {
        /** @var \DreamFactory\Core\User\Services\User $userService */
        $userService = ServiceManager::getService('user');
        if (!$userService->allowOpenRegistration) {
            throw new ForbiddenException('Open Registration is not enabled.');
        }

        /** @type User $user */
        $user = User::create($data);

        if (!empty($userService->openRegEmailServiceId)) {
            $this->sendConfirmation($user, $userService->openRegEmailServiceId, $userService->openRegEmailTemplateId);
        } else if (!empty($data['password'])) {
            $user->password = $data['password'];
            $user->save();
        }

        if (!empty($userService->openRegRoleId)) {
            User::applyDefaultUserAppRole($user, $userService->openRegRoleId);
        }
        if (!empty($serviceId)) {
            User::applyAppRoleMapByService($user, $serviceId);
        }

        return $user;
    }

    /**
     * @param           $user User
     * @param           $emailServiceId
     * @param           $emailTemplateId
     * @param bool|true $deleteOnError
     *
     * @throws \DreamFactory\Core\Exceptions\InternalServerErrorException
     */
    protected static function sendConfirmation($user, $emailServiceId, $emailTemplateId, $deleteOnError = true)
    {
        try {
            if (empty($emailServiceId)) {
                throw new InternalServerErrorException('No email service configured for user invite. See system configuration.');
            }

            if (empty($emailTemplateId)) {
                throw new InternalServerErrorException("No default email template for user invite.");
            }

            /** @var EmailServiceInterface $emailService */
            $emailService = ServiceManager::getServiceById($emailServiceId);
            /** @var EmailTemplate $emailTemplate */
            /** @noinspection PhpUndefinedMethodInspection */
            $emailTemplate = EmailTemplate::find($emailTemplateId);

            if (empty($emailTemplate)) {
                throw new InternalServerErrorException("No data found in default email template for user invite.");
            }

            try {
                $email = $user->email;
                $user->confirm_code = static::generateConfirmationCode(\Config::get('df.confirm_code_length', 32));
                $user->save();
                $templateData = $emailTemplate->toArray();
                $data = array_merge($templateData, [
                    'to'             => $email,
                    'confirm_code'   => $user->confirm_code,
                    'link'           => url(\Config::get('df.confirm_register_url')) .
                        '?code=' . $user->confirm_code .
                        '&email=' . $email .
                        '&username=' . strip_tags($user->username),
                    'first_name'     => strip_tags($user->first_name),
                    'last_name'      => strip_tags($user->last_name),
                    'name'           => strip_tags($user->name),
                    'email'          => $user->email,
                    'phone'          => strip_tags($user->phone),
                    'content_header' => array_get($templateData, 'subject', 'Confirm your DreamFactory account.'),
                    'app_name'       => \Config::get('app.name'),
                    'instance_name'  => \Config::get('app.name'), // older templates
                ]);
            } catch (\Exception $e) {
                throw new InternalServerErrorException("Error creating user confirmation.\n{$e->getMessage()}",
                    $e->getCode());
            }

            $bodyText = $emailTemplate->body_text;
            if (empty($bodyText)) {
                //Strip all html tags.
                $bodyText = strip_tags($emailTemplate->body_html);
                //Change any multi spaces to a single space for clarity.
                $bodyText = preg_replace('/ +/', ' ', $bodyText);
            }

            $emailService->sendEmail($data, $bodyText, $emailTemplate->body_html);
        } catch (\Exception $e) {
            if ($deleteOnError) {
                $user->delete();
            }
            throw new InternalServerErrorException("Error processing user confirmation.\n{$e->getMessage()}",
                $e->getCode());
        }
    }

    /**
     * Generates a user confirmation code. (min 5 char)
     *
     * @param int $length
     *
     * @return string
     */
    public static function generateConfirmationCode($length = 32)
    {
        $length = ($length < 5) ? 5 : (($length > 32) ? 32 : $length);
        $range = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $range[rand(0, strlen($range) - 1)];
        }

        return $code;
    }
}
