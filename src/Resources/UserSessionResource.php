<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Utility\CacheUtilities;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Utility\Session;
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
            'email'    => $this->getPayloadData('email'),
            'password' => $this->getPayloadData('password')
        ];

        return $this->handleLogin($credentials, boolval($this->getPayloadData('remember_me')));
    }

    /**
     * Refreshes current JWT.
     *
     * @return array
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    protected function handlePUT()
    {
        JWTUtilities::refreshToken();
        return Session::getPublicInfo();
    }

    /**
     * Logs out user
     *
     * @return array
     */
    protected function handleDELETE()
    {
        Session::logout();

        //Clear everything in session.
        Session::flush();

        return ['success' => true];
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
    protected function handleLogin(array $credentials = [], $remember = false)
    {
        $email = ArrayUtils::get($credentials, 'email');
        if (empty($email)) {
            throw new BadRequestException('Login request is missing required email.');
        }

        $password = ArrayUtils::get($credentials, 'password');
        if (empty($password)) {
            throw new BadRequestException('Login request is missing required password.');
        }

        $credentials['is_active'] = 1;

        // if user management not available then only system admins can login.
        if (!class_exists('\DreamFactory\Core\User\Resources\System\User')) {
            $credentials['is_sys_admin'] = 1;
        }

        if (Session::authenticate($credentials, $remember)) {
            return Session::getPublicInfo();
        } else {
            throw new UnauthorizedException('Invalid credentials supplied.');
        }
    }
}