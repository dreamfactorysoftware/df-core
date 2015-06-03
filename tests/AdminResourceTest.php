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

use DreamFactory\Library\Utility\Enums\Verbs;
use Illuminate\Support\Arr;
use DreamFactory\Library\Utility\Scalar;
use DreamFactory\Rave\Utility\ServiceHandler;
use DreamFactory\Rave\Models\User;

class AdminResourceTest extends \DreamFactory\Rave\Testing\UserResourceTestCase
{
    const RESOURCE = 'admin';

    protected function adminCheck( $records )
    {
        foreach ( $records as $user )
        {
            $userModel = \DreamFactory\Rave\Models\User::find( $user['id'] );

            if ( !Scalar::boolval( $userModel->is_sys_admin ) )
            {
                return false;
            }
        }

        return true;
    }

    public function testNonAdmin()
    {
        $user = $this->user1;
        $this->makeRequest( Verbs::POST, 'user', [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], [ $user ] );

        //Using a new instance here. Prev instance is set for user resource.
        $this->service = \DreamFactory\Rave\Utility\ServiceHandler::getService( 'system' );

        $rs = $this->makeRequest( Verbs::GET, static::RESOURCE );
        $content = $rs->getContent();

        $this->assertEquals( 1, count( $content['record'] ) );
        $this->assertEquals( 'Rave Admin', Arr::get( $content, 'record.0.name' ) );
    }

    /************************************************
     * Session sub-resource test
     ************************************************/

    public function testSessionNotFound()
    {
        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\NotFoundException' );
        $this->makeRequest( Verbs::GET, static::RESOURCE . '/session' );
    }

    public function testUnauthorizedSessionRequest()
    {
        $user = $this->user1;
        $this->makeRequest( Verbs::POST, 'user', [ 'fields' => '*', 'related' => 'user_lookup_by_user_id' ], [ $user ] );

        Auth::attempt(['email' => $user['email'], 'password' => $user['password']]);

        //Using a new instance here. Prev instance is set for user resource.
        $this->service = ServiceHandler::getService( 'system' );

        $this->setExpectedException('\DreamFactory\Rave\Exceptions\UnauthorizedException');
        $this->makeRequest(Verbs::GET, static::RESOURCE.'/session');
    }

    public function testLogin()
    {
        $user = $this->createUser( 1 );

        $payload = [ 'email' => $user['email'], 'password' => $this->user1['password'] ];

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE.'/session', [ ], $payload );
        $content = $rs->getContent();

        $this->assertEquals( $user['first_name'], $content['first_name'] );
        $this->assertTrue( !empty( $content['session_id'] ) );
    }

    public function testSessionBadPatchRequest()
    {
        $user = $this->createUser(1);
        $payload = ['name'=>'foo'];

        $this->setExpectedException('\DreamFactory\Rave\Exceptions\BadRequestException');
        $this->makeRequest(Verbs::PATCH, static::RESOURCE.'/session/'.$user['id'], [], $payload);
    }

    public function testLogout()
    {
        $user = $this->createUser( 1 );
        $payload = [ 'email' => $user['email'], 'password' => $this->user1['password'] ];
        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE.'/session', [ ], $payload );
        $content = $rs->getContent();

        $this->assertTrue( !empty( $content['session_id'] ) );

        $rs = $this->makeRequest(Verbs::DELETE, static::RESOURCE.'/session');
        $content = $rs->getContent();

        $this->assertTrue($content['success']);

        $this->setExpectedException( '\DreamFactory\Rave\Exceptions\NotFoundException' );
        $this->makeRequest( Verbs::GET, static::RESOURCE . '/session' );
    }

    /************************************************
     * Password sub-resource test
     ************************************************/

    public function testGET()
    {
        $this->setExpectedException('\DreamFactory\Rave\Exceptions\BadRequestException');
        $this->makeRequest(Verbs::GET, static::RESOURCE.'/password');
    }

    public function testDELETE()
    {
        $this->setExpectedException('\DreamFactory\Rave\Exceptions\BadRequestException');
        $this->makeRequest(Verbs::DELETE, static::RESOURCE.'/password');
    }

    public function testPasswordChange()
    {
        $user = $this->createUser( 1 );

        $this->makeRequest( Verbs::POST, static::RESOURCE . '/session', [ ], [ 'email' => $user['email'], 'password' => $this->user1['password'] ] );
        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE . '/password', [ ], [ 'old_password' => $this->user1['password'], 'new_password' => '123456' ] );
        $content = $rs->getContent();
        $this->assertTrue($content['success']);

        $this->makeRequest(Verbs::DELETE, static::RESOURCE.'/session');

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE . '/session', [ ], [ 'email' => $user['email'], 'password' => '123456' ] );
        $content = $rs->getContent();
        $this->assertTrue(!empty($content['session_id']));
    }

    public function testPasswordResetUsingSecurityQuestion()
    {
        $user = $this->createUser( 1 );

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE . '/password', [ 'reset' => 'true' ], [ 'email' => $user['email'] ] );
        $content = $rs->getContent();

        $this->assertEquals( $this->user1['security_question'], $content['security_question'] );

        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE . '/password',
            [ ],
            [ 'email' => $user['email'], 'security_answer' => $this->user1['security_answer'], 'new_password' => '778877' ]
        );
        $content = $rs->getContent();
        $this->assertTrue( $content['success'] );

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE . '/session', [ ], [ 'email' => $user['email'], 'password' => '778877' ] );
        $content = $rs->getContent();
        $this->assertTrue( !empty( $content['session_id'] ) );
    }

    public function testPasswordResetUsingConfirmationCode()
    {
        Arr::set($this->user2, 'email', 'arif@dreamfactory.com');
        $user = $this->createUser(2);

        Config::set('mail.pretend', true);
        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE . '/password', [ 'reset' => 'true' ], [ 'email' => $user['email'] ] );
        $content = $rs->getContent();
        $this->assertTrue($content['success']);

        /** @var User $userModel */
        $userModel = User::find($user['id']);
        $code = $userModel->confirm_code;

        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE . '/password',
            [ 'login' => 'true' ],
            [ 'email' => $user['email'], 'code' => $code, 'new_password' => '778877' ]
        );
        $content = $rs->getContent();
        $this->assertTrue( $content['success'] );
        $this->assertTrue(Auth::check());

        $userModel = User::find($user['id']);
        $this->assertEquals('y', $userModel->confirm_code);

        $rs = $this->makeRequest( Verbs::POST, static::RESOURCE . '/session', [ ], [ 'email' => $user['email'], 'password' => '778877' ] );
        $content = $rs->getContent();
        $this->assertTrue(!empty($content['session_id']));
    }
}