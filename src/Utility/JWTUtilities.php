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

        $claims = ['user_id' => $userId, 'forever' => $forever];
        /** @type Payload $payload */
        $payload = JWTFactory::make($claims);
        /** @type Token $token */
        $token = \JWTAuth::encode($payload);
        $tokenValue = $token->get();
        static::setTokenMap($payload, $tokenValue);

        return $tokenValue;
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