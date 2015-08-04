<?php

use DreamFactory\Library\Utility\Enums\Verbs;
use Illuminate\Support\Arr;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Session;

class AdminResourceTest extends \DreamFactory\Core\Testing\UserResourceTestCase
{
    const RESOURCE = 'admin';

    protected function adminCheck($records)
    {
        if(isset($records[static::$wrapper])){
            $records = $records[static::$wrapper];
        }
        foreach ($records as $user) {
            $userModel = \DreamFactory\Core\Models\User::find($user['id']);

            if (!$userModel->is_sys_admin) {
                return false;
            }
        }

        return true;
    }

    public function testNonAdmin()
    {
        $user = $this->user1;
        $this->makeRequest(Verbs::POST, 'user', [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'], [$user]);

        //Using a new instance here. Prev instance is set for user resource.
        $this->service = \DreamFactory\Core\Utility\ServiceHandler::getService('system');

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE);
        $content = $rs->getContent();

        $this->assertEquals(1, count($content[static::$wrapper]));
        $this->assertEquals('DF Admin', Arr::get($content, static::$wrapper . '.0.name'));
    }

    /************************************************
     * Session sub-resource test
     ************************************************/

    public function testSessionNotFound()
    {
        $this->setExpectedException('\DreamFactory\Core\Exceptions\NotFoundException');
        $this->makeRequest(Verbs::GET, static::RESOURCE . '/session');
    }

    public function testUnauthorizedSessionRequest()
    {
        $user = $this->user1;
        $this->makeRequest(Verbs::POST, 'user', [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'], [$user]);

        Session::authenticate(['email' => $user['email'], 'password' => $user['password']]);

        //Using a new instance here. Prev instance is set for user resource.
        $this->service = ServiceHandler::getService('system');

        $this->setExpectedException('\DreamFactory\Core\Exceptions\UnauthorizedException');
        $this->makeRequest(Verbs::GET, static::RESOURCE . '/session');
    }

    public function testLogin()
    {
        $user = $this->createUser(1);

        $payload = ['email' => $user['email'], 'password' => $this->user1['password']];

        $rs = $this->makeRequest(Verbs::POST, static::RESOURCE . '/session', [], $payload);
        $content = $rs->getContent();
        $token = $content['session_token'];
        $tokenMap = DB::table('token_map')->where('token', $token)->get();

        $this->assertEquals($user['first_name'], $content['first_name']);
        $this->assertTrue(!empty($token));
        $this->assertTrue(!empty($tokenMap));
    }

    public function testSessionBadPatchRequest()
    {
        $user = $this->createUser(1);
        $payload = ['name' => 'foo'];

        $this->setExpectedException('\DreamFactory\Core\Exceptions\BadRequestException');
        $this->makeRequest(Verbs::PATCH, static::RESOURCE . '/session/' . $user['id'], [], $payload);
    }

    public function testLogout()
    {
        $user = $this->createUser(1);
        $payload = ['email' => $user['email'], 'password' => $this->user1['password']];
        $rs = $this->makeRequest(Verbs::POST, static::RESOURCE . '/session', [], $payload);
        $content = $rs->getContent();
        $token = $content['session_token'];
        $tokenMap = DB::table('token_map')->where('token', $token)->get();
        $this->assertTrue(!empty($token));
        $this->assertTrue(!empty($tokenMap));

        $rs = $this->makeRequest(Verbs::DELETE, static::RESOURCE . '/session', ['session_token' => $token]);
        $content = $rs->getContent();
        $tokenMap = DB::table('token_map')->where('token', $token)->get();
        $this->assertTrue($content['success']);
        $this->assertTrue(empty($tokenMap));

        $this->setExpectedException('\DreamFactory\Core\Exceptions\NotFoundException');
        $this->makeRequest(Verbs::GET, static::RESOURCE . '/session');
    }

    /************************************************
     * Password sub-resource test
     ************************************************/

    public function testGET()
    {
        $this->setExpectedException('\DreamFactory\Core\Exceptions\BadRequestException');
        $this->makeRequest(Verbs::GET, static::RESOURCE . '/password');
    }

    public function testDELETE()
    {
        $this->setExpectedException('\DreamFactory\Core\Exceptions\BadRequestException');
        $this->makeRequest(Verbs::DELETE, static::RESOURCE . '/password');
    }

    public function testPasswordChange()
    {
        $user = $this->createUser(1);

        $this->makeRequest(
            Verbs::POST,
            static::RESOURCE . '/session',
            [],
            ['email' => $user['email'], 'password' => $this->user1['password']]
        );
        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE . '/password',
            [],
            ['old_password' => $this->user1['password'], 'new_password' => '123456']
        );
        $content = $rs->getContent();
        $this->assertTrue($content['success']);

        $this->makeRequest(Verbs::DELETE, static::RESOURCE . '/session');

        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE . '/session',
            [],
            ['email' => $user['email'], 'password' => '123456']
        );
        $content = $rs->getContent();
        $token = $content['session_token'];
        $tokenMap = DB::table('token_map')->where('token', $token)->get();
        $this->assertTrue(!empty($token));
        $this->assertTrue(!empty($tokenMap));
    }

    public function testPasswordResetUsingSecurityQuestion()
    {
        $user = $this->createUser(1);

        $rs =
            $this->makeRequest(Verbs::POST, static::RESOURCE . '/password', ['reset' => 'true'],
                ['email' => $user['email']]);
        $content = $rs->getContent();

        $this->assertEquals($this->user1['security_question'], $content['security_question']);

        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE . '/password',
            [],
            ['email'           => $user['email'],
             'security_answer' => $this->user1['security_answer'],
             'new_password'    => '778877'
            ]
        );
        $content = $rs->getContent();
        $this->assertTrue($content['success']);

        $rs =
            $this->makeRequest(Verbs::POST, static::RESOURCE . '/session', [],
                ['email' => $user['email'], 'password' => '778877']);
        $content = $rs->getContent();
        $token = $content['session_token'];
        $tokenMap = DB::table('token_map')->where('token', $token)->get();
        $this->assertTrue(!empty($token));
        $this->assertTrue(!empty($tokenMap));
    }

    public function testPasswordResetUsingConfirmationCode()
    {
        Arr::set($this->user2, 'email', 'arif@dreamfactory.com');
        $user = $this->createUser(2);

        Config::set('mail.pretend', true);
        $rs =
            $this->makeRequest(Verbs::POST, static::RESOURCE . '/password', ['reset' => 'true'],
                ['email' => $user['email']]);
        $content = $rs->getContent();
        $this->assertTrue($content['success']);

        /** @var User $userModel */
        $userModel = User::find($user['id']);
        $code = $userModel->confirm_code;

        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE . '/password',
            ['login' => 'true'],
            ['email' => $user['email'], 'code' => $code, 'new_password' => '778877']
        );
        $content = $rs->getContent();
        $this->assertTrue($content['success']);
        $this->assertTrue(\DreamFactory\Core\Utility\Session::isAuthenticated());

        $userModel = User::find($user['id']);
        $this->assertEquals('y', $userModel->confirm_code);

        $rs = $this->makeRequest(Verbs::POST, static::RESOURCE . '/session', [],
                ['email' => $user['email'], 'password' => '778877']);
        $content = $rs->getContent();
        $token = $content['session_token'];
        $tokenMap = DB::table('token_map')->where('token', $token)->get();
        $this->assertTrue(!empty($token));
        $this->assertTrue(!empty($tokenMap));
    }
}