<?php

namespace DreamFactory\Core\Utility;

use Carbon\Carbon;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\UserAppRole;
use Tymon\JWTAuth\Exceptions\JWTException;
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
        $customClaims = ['user_id' => $userId, 'forever' => $forever];
        $customClaimFields = config('jwt.custom_claims');
        if (!empty($customClaimFields)) {
            /** @var User $user */
            $user = User::find($userId);
            if (!empty($user)) {
                $user = $user->toArray();
                foreach ($customClaimFields as $ccf) {
                    $value = array_get($user, $ccf);
                    if (!empty($value)) {
                        $customClaims[$ccf] = $value;
                    }
                }
            }
        }

        /** @type Payload $payload */
        /** @noinspection PhpUndefinedMethodInspection */
        $payload = JWTFactory::sub(md5($email))->customClaims($customClaims)->make();
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
        $userKey = $payload->get('sub');
        $userInfo = ($userId) ? User::getCachedInfo($userId) : null;

        if (!empty($userInfo) && $userKey === md5($userInfo['email'])) {
            return true;
        } else {
            throw new TokenInvalidException('User verification failed.');
        }
    }

    /**
     * Refreshes a JWT token.
     * Re-issues new JWT token if the original token is marked as 'forever'
     *
     * NOTE: No tokens (including forever tokens) can ever be
     * refreshed after refresh TTL has passed.
     *
     * @return string
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    public static function refreshToken()
    {
        $token = Session::getSessionToken();
        //-----------------------------------------------------------------
        // This will avoid TokenExpiredException error as long as we are
        // still in the refresh TTL window. Will still allow throwing
        // TokenExpiredException after refresh TTL has passed.
        //
        // NOTE: No tokens (including forever tokens) can ever be
        // refreshed after refresh TTL has passed.
        JWTAuth::manager()->setRefreshFlow();
        //-----------------------------------------------------------------

        try {
            JWTAuth::setToken($token);

            // Checks for token validity - Expired TTL, Expired Refresh TTL, Blacklisted.
            JWTAuth::checkOrFail();

            // Retrieve the 'forever' flag (remember_me flag set to true during login.)
            $payloadArray = JWTAuth::manager()->getJWTProvider()->decode($token);
            $forever = boolval(array_get($payloadArray, 'forever'));
            // Retrieve the user associated with the token
            $userId = array_get($payloadArray, 'user_id');
            /** @var User $user */
            $user = User::find($userId);

            // If token is marked forever then re-issue a new token (not refresh)
            // in order to bump up the refresh TTL for the new token.
            if ($forever) {
                // Clear any existing claims. We will be re-issuing a new token with new claims
                // based on the same user of the original token.
                JWTFactory::claims([]);
                // Re-issue new token.
                Session::setUserInfoWithJWT($user, $forever);
            } else {
                $newToken = JWTAuth::refresh(true);
                JWTAuth::setToken($newToken);
                $payload = JWTAuth::getPayload();
                // Add new token to our token mapping
                static::setTokenMap($payload, $newToken);
                Session::setSessionToken($newToken);
                $userInfo = $user->toArray();
                $userInfo['is_sys_admin'] = $user->is_sys_admin;
                Session::setUserInfo($userInfo);
            }
            // Invalidate and remove from our token mapping
            static::invalidate($token);
        } catch (JWTException $e) {
            throw new UnauthorizedException('Token refresh failed. ' . $e->getMessage());
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
        DB::table('token_map')->where('user_id', $userId)->get()->each(function ($map){
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