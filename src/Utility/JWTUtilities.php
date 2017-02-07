<?php
namespace DreamFactory\Core\Utility;

use Carbon\Carbon;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\UserAppRole;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Token;
use Tymon\JWTAuth\Payload;
use DB;
use JWTAuth;
use JWTFactory;

class JWTUtilities
{
    /**
     * @param      $userId
     * @param      $email
     * @param bool $forever
     *
     * @return string
     */
    public static function makeJWTByUser($userId, $email, $forever = false)
    {
        if (\Config::get('df.allow_forever_sessions') === false) {
            $forever = false;
        }

        /** @type Payload $payload */
        /** @noinspection PhpUndefinedMethodInspection */
        $payload = JWTFactory::sub($userId)->user_id($userId)->email($email)->forever($forever)->make();
        /** @type Token $token */
        $token = JWTAuth::manager()->encode($payload);
        $tokenValue = $token->get();
        static::setTokenMap($payload, $tokenValue);

        return $tokenValue;
    }

    /**
     * Verifies JWT user.
     *
     * @param Payload $payload
     *
     * @return bool
     * @throws TokenInvalidException
     */
    public static function verifyUser($payload)
    {
        $userId = $payload->get('user_id');
        $email = $payload->get('email');
        $userInfo = ($userId) ? User::getCachedInfo($userId) : null;

        if (!empty($userInfo) && $email === $userInfo['email']) {
            return true;
        } else {
            throw new TokenInvalidException('User verification failed.');
        }
    }

    /**
     * @return string
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    public static function refreshToken()
    {
        $token = Session::getSessionToken();
        try {
            JWTAuth::setToken($token);
            $newToken = JWTAuth::refresh();
            $payload = JWTAuth::getPayload();
            $userId = $payload->get('user_id');
            /** @var User $user */
            $user = User::find($userId);
            $userInfo = $user->toArray();
            $userInfo['is_sys_admin'] = $user->is_sys_admin;
            Session::setSessionToken($newToken);
            Session::setUserInfo($userInfo);
            static::setTokenMap($payload, $newToken);
        } catch (TokenExpiredException $e) {
            $payloadArray = JWTAuth::manager()->getJWTProvider()->decode($token);
            $forever = boolval(array_get($payloadArray, 'forever'));
            if ($forever) {
                $userId = array_get($payloadArray, 'user_id');
                $user = User::find($userId);
                Session::setUserInfoWithJWT($user, $forever);
            } else {
                throw new UnauthorizedException($e->getMessage());
            }
        }

        return Session::getSessionToken();
    }

    public static function isForever($token)
    {
        $payloadArray = JWTAuth::manager()->getJWTProvider()->decode($token);
        $forever = boolval(array_get($payloadArray, 'forever'));

        return $forever;
    }

    /**
     * @param $token
     */
    public static function invalidate($token)
    {
        JWTAuth::setToken($token);
        $payload = JWTAuth::manager()->getJWTProvider()->decode($token);
        $userId = array_get($payload, 'user_id');
        $exp = array_get($payload, 'exp');
        static::removeTokenMap($userId, $exp);
        try {
            JWTAuth::invalidate();
        } catch (TokenExpiredException $e) {
            //If the token is expired already then do nothing here. The token map is already removed above.
        }
    }

    public static function clearAllExpiredTokenMaps()
    {
        $now = Carbon::now()->format('U');

        return DB::table('token_map')->where('exp', '<', $now)->delete();
    }

    public static function invalidateTokenByUserId($userId)
    {
        DB::table('token_map')->where('user_id', $userId)->get()->each(function($map) {
            try {
                JWTAuth::setToken($map->token);
                JWTAuth::invalidate();
            } catch (TokenExpiredException $e) {
                //If the token is expired already then do nothing here.
            }
        });

        return DB::table('token_map')->where('user_id', $userId)->delete();
    }

    public static function invalidateTokenByRoleId($roleId)
    {
        $userAppRole = UserAppRole::whereRoleId($roleId)->get(['user_id']);

        if (!empty($userAppRole) && is_array($userAppRole)) {
            foreach ($userAppRole as $uar) {
                static::invalidateTokenByUserId($uar->user_id);
            }
        }
    }

    public static function invalidateTokenByAppId($appId)
    {
        $userAppRole = UserAppRole::whereAppId($appId)->get(['user_id']);

        if (!empty($userAppRole) && is_array($userAppRole)) {
            foreach ($userAppRole as $uar) {
                static::invalidateTokenByUserId($uar->user_id);
            }
        }
    }

    /**
     * @param $userId
     * @param $exp
     *
     * @return int
     */
    protected static function removeTokenMap($userId, $exp)
    {
        return DB::table('token_map')->where('user_id', $userId)->where('exp', $exp)->delete();
    }

    /**
     * @param Payload $payload
     * @param string  $token
     *
     * @return bool
     */
    protected static function setTokenMap($payload, $token)
    {
        $map = [
            'user_id' => $payload->get('user_id'),
            'iat'     => $payload->get('iat'),
            'exp'     => $payload->get('exp'),
            'token'   => $token
        ];

        return DB::table('token_map')->insert($map);
    }
}