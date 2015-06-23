<?php

namespace DreamFactory\Core\Resources;

use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Library\Utility\ArrayUtils;
use DreamFactory\Core\Utility\Session;
use Carbon\Carbon;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Token;

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
        $apiKey = $this->request->getApiKey();
        if (empty($apiKey)) {
            $apiKey = $this->getPayloadData('api_key');
        }

        $credentials = [
            'email'    => $this->getPayloadData('email'),
            'password' => $this->getPayloadData('password')
        ];

        return $this->handleLogin($apiKey, $credentials, boolval($this->getPayloadData('remember_me')));
    }

    /**
     * Logs out user
     *
     * @return array
     */
    protected function handleDELETE()
    {
        \Auth::logout();

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
    protected function handleLogin($apiKey, array $credentials = [], $remember = false)
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

        if (\Auth::attempt($credentials, $remember)) {
            $user = \Auth::user();
            $user->update(['last_login_date' => Carbon::now()->toDateTimeString()]);
            $userId = $user->id;

            /** @var App $app */
            $app = App::with(
                [
                    'role_by_user_to_app_to_role' => function ($q) use ($userId){
                        $q->whereUserId($userId);
                    }
                ]
            )->whereApiKey($apiKey)->first();

            if (empty($app)) {
                throw new BadRequestException('Invalid API Key.');
            }

            /** @var Role $role */
            $role = $app->getRelation('role_by_user_to_app_to_role')->first();

            if (empty($role)) {
                $app->load('role_by_role_id');
                /** @var Role $role */
                $role = $app->getRelation('role_by_role_id');
            }

            if (empty($role)) {
                throw new InternalServerErrorException('Unexpected error occurred. Role not found for Application.');
            }

            $sessionInfo = static::getSessionInfo($user, $role, $app);

            return $sessionInfo;

            //Session::setUserInfo($user);

            //return Session::getPublicInfo();
        } else {
            throw new BadRequestException('Invalid credentials supplied.');
        }
    }

    protected static function getSessionInfo($user, $role, $app)
    {
        if ((empty($user)) && (empty($app))) {
            throw new UnauthorizedException('There is no valid session for the current request.');
        }

        $claims = [
            'user_id' => $user->id,
            'role_id' => $role->id,
            'app_id'  => $app->id,
            'api_key' => $app->api_key
        ];

        $payload = JWTFactory::make($claims);
        /** @type Token $token */
        $token = JWTAuth::encode($payload);

        $sessionData = [
            'session_id'      => $token->get(),
            'id'              => $user->id,
            'name'            => $user->name,
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'email'           => $user->email,
            'is_sys_admin'    => $user->is_sys_admin,
            'last_login_date' => $user->last_login_date,
            'host'            => gethostname()
        ];

        if (!$user->is_sys_admin && !empty($role)) {
            ArrayUtils::set($sessionData, 'role', $role->name);
            ArrayUtils::set($sessionData, 'role_id', $role->id);
        }

        return $sessionData;
    }
}