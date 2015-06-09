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

use DreamFactory\Rave\Models\User;
use DreamFactory\Rave\Models\App;
use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Rave\Utility\ServiceHandler;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Rave\Utility\Session;
use Illuminate\Support\Arr;

class AccessCheckMiddlewareTest extends \DreamFactory\Rave\Testing\TestCase
{
    public function tearDown()
    {
        User::whereEmail('jdoe@dreamfactory.com')->delete();
    }

    public function testSysAdmin()
    {
        $user = User::find(1);

        $this->be($user);
        Session::setUserInfo($user);
        $this->call(Verbs::GET, '/api/v2/system');

        $this->assertTrue(Session::isSysAdmin());
        $this->assertEquals(null, session('rsa.role.id'));
        $rsa = session('rsa.role.services');
        $this->assertTrue(empty($rsa));
    }

    public function testApiKeyRole()
    {
        $app = App::find(1);
        $apiKey = $app->api_key;

        $this->call(Verbs::GET, '/api/v2/system', ['api_key'=>$apiKey]);

        $this->assertFalse(Session::isSysAdmin());
        $this->assertEquals(1, Session::getWithApiKey('role.id'));
        $rsa = Session::getWithApiKey('role.services');
        $this->assertTrue(!empty($rsa));
    }

    public function testApiKeyUserRole()
    {
        $user = [
            'name'              => 'John Doe',
            'first_name'        => 'John',
            'last_name'         => 'Doe',
            'email'             => 'jdoe@dreamfactory.com',
            'password'          => 'test1234',
            'security_question' => 'Make of your first car?',
            'security_answer'   => 'mazda',
            'is_active'         => 1
        ];

        $this->service = ServiceHandler::getService('system');
        $rs = $this->makeRequest(Verbs::POST, 'user', [], [$user]);
        $data = $rs->getContent();
        $userId = Arr::get($data, 'id');

        \DreamFactory\Rave\Models\UserAppRole::create(['user_id'=>$userId, 'app_id'=>2, 'role_id'=>1]);
        $app = App::find(2);
        $apiKey = $app->api_key;

        $myUser = User::find($userId);
        $this->be($myUser);
        Session::setUserInfo($myUser);
        $this->call(Verbs::GET, '/api/v2/system', [], [], [], ['HTTP_X_DREAMFACTORY_API_KEY'=>$apiKey]);

        $this->assertFalse(Session::isSysAdmin());
        $this->assertEquals(1, Session::getWithApiKey('role.id'));
        $rsa = Session::getWithApiKey('role.services');
        $this->assertTrue(!empty($rsa));
    }
}