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

namespace DreamFactory\Core\Utility;

use Carbon\Carbon;
use DreamFactory\Core\Exceptions\UnauthorizedException;
use DreamFactory\Core\Models\User;
use DreamFactory\Library\Utility\ArrayUtils;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTFactory;
use Tymon\JWTAuth\Token;
use Tymon\JWTAuth\Payload;

class JWTUtilities
{
    /**
     * @param      $userId
     * @param bool $forever
     *
     * @return string
     */
    public static function makeJWTByUserId($userId, $forever = false)
    {
        if (\Config::get('df.allow_forever_sessions') === false) {
            $forever = false;
        }

        $claims = ['sub' => $userId, 'user_id' => $userId, 'forever' => $forever];
        /** @type Payload $payload */
        $payload = JWTFactory::make($claims);
        /** @type Token $token */
        $token = \JWTAuth::encode($payload);
        $tokenValue = $token->get();
        static::setTokenMap($payload, $tokenValue);

        return $tokenValue;
    }

    /**
     * @return string
     * @throws \DreamFactory\Core\Exceptions\UnauthorizedException
     */
    public static function refreshToken()
    {
        $token = Session::getSessionToken();
        try {
            $newToken = \JWTAuth::refresh($token);
            $payload = \JWTAuth::getPayload($newToken);
            $userId = $payload->get('user_id');
            $user = User::find($userId);
            $userInfo = $user->toArray();
            ArrayUtils::set($userInfo, 'is_sys_admin', $user->is_sys_admin);
            Session::setSessionToken($newToken);
            Session::setUserInfo($userInfo);
            static::setTokenMap($payload, $newToken);
        } catch (TokenExpiredException $e) {
            $payloadArray = \JWTAuth::manager()->getJWTProvider()->decode($token);
            $forever = boolval(ArrayUtils::get($payloadArray, 'forever'));
            if ($forever) {
                $userId = ArrayUtils::get($payloadArray, 'user_id');
                $user = User::find($userId);
                Session::setUserInfoWithJWT($user, $forever);
            } else {
                throw new UnauthorizedException($e->getMessage());
            }
        }

        return Session::getSessionToken();
    }

    /**
     * @param $token
     */
    public static function invalidate($token)
    {
        \JWTAuth::setToken($token);
        /** @type Payload $payload */
        $payload = \JWTAuth::getPayload();
        $userId = $payload->get('user_id');
        $exp = $payload->get('exp');
        static::removeTokenMap($userId, $exp);
        \JWTAuth::invalidate();
    }

    public static function clearAllExpiredTokenMaps()
    {
        $now = Carbon::now()->format('U');

        return \DB::table('token_map')->where('exp', '<', $now)->delete();
    }

    /**
     * @param $userId
     * @param $exp
     *
     * @return int
     */
    protected static function removeTokenMap($userId, $exp)
    {
        return \DB::table('token_map')->where('user_id', $userId)->where('exp', $exp)->delete();
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

        return \DB::table('token_map')->insert($map);
    }
}