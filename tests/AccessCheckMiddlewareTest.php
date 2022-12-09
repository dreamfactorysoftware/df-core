<?php
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Utility\JWTUtilities;
use DreamFactory\Core\Models\Role;
use Illuminate\Support\Arr;

class AccessCheckMiddlewareTest extends \DreamFactory\Core\Testing\TestCase
{
    public function tearDown(): void
    {
        User::whereEmail('jdoe@dreamfactory.com')->delete();
        Role::whereName('test_role')->delete();
        App::whereId(1)->update(['role_id' => null]);
    }

    public function testSysAdmin()
    {
        $this->markTestSkipped('must be revisited.');
        $user = User::find(1);
        $token = JWTUtilities::makeJWTByUser($user->id, $user->email);

        $this->call(Verbs::GET, '/api/v2/system', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);

        $this->assertTrue(Session::isSysAdmin(), 'assertion 1');
        $this->assertEquals(null, session('admin.role.id'), 'assertion 2');
        $rsa = session('role.services');
        $this->assertTrue(empty($rsa), 'assertion 7');
    }

    public function testApiKeyRole()
    {
        $this->markTestSkipped('must be revisited.');
        $app = App::find(1);
        $apiKey = $app->api_key;

        $role = [
            'resource' => [
                [
                    'name'                           => 'test_role',
                    'is_active'                      => true,
                    'role_service_access_by_role_id' => [
                        ['service_id' => 1, 'component' => 'config', 'verb_mask' => 1, 'requestor_mask' => 1]
                    ]
                ]
            ]
        ];

        $this->service = ServiceManager::getService('system');
        $rs = $this->makeRequest(Verbs::POST, 'role', [], $role);
        $data = $rs->getContent();
        $roleId = Arr::get($data, static::$wrapper . '.0.id');
        $app->role_id = $roleId;
        $app->save();

        $this->call(Verbs::GET, '/api/v2/system', ['api_key' => $apiKey]);

        $this->assertFalse(Session::isSysAdmin());
        $this->assertEquals($roleId, Session::get('role.id'));
        $rsa = Session::get('role.services');
        $this->assertTrue(!empty($rsa));
    }

    public function testApiKeyUserRole()
    {
        $this->markTestSkipped('must be revisited.');
        $user = [
            'name'              => 'John Doe',
            'first_name'        => 'John',
            'last_name'         => 'Doe',
            'email'             => 'jdoe@dreamfactory.com',
            'password'          => 'test1234',
            'security_question' => 'Make of your first car?',
            'security_answer'   => 'mazda',
            'is_active'         => true
        ];

        $role = [
            'name'                           => 'test_role',
            'is_active'                      => true,
            'role_service_access_by_role_id' => [
                ['service_id' => 1, 'component' => 'config', 'verb_mask' => 1, 'requestor_mask' => 1]
            ]
        ];

        $this->service = ServiceManager::getService('system');
        $rs = $this->makeRequest(Verbs::POST, 'user', [], [$user]);
        $data = $rs->getContent();
        $userId = Arr::get($data, static::$wrapper . '.0.id');

        $this->service = ServiceManager::getService('system');
        $rs = $this->makeRequest(Verbs::POST, 'role', [], [$role]);
        $data = $rs->getContent();
        $roleId = Arr::get($data, static::$wrapper . '.0.id');

        \DreamFactory\Core\Models\UserAppRole::create(['user_id' => $userId, 'app_id' => 1, 'role_id' => $roleId]);
        $app = App::find(1);
        $apiKey = $app->api_key;

        $myUser = User::find($userId);
        $token = JWTUtilities::makeJWTByUser($myUser->id, $myUser->email);
        $this->call(
            Verbs::GET, '/api/v2/system',
            [],
            [],
            [],
            ['HTTP_X_DREAMFACTORY_API_KEY' => $apiKey, 'HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);

        $this->assertFalse(Session::isSysAdmin());
        $this->assertEquals($roleId, Session::get('role.id'));
        $rsa = Session::get('role.services');
        $this->assertTrue(!empty($rsa));
    }

    public function testPathException()
    {
        $this->markTestSkipped('must be revisited.');
        $rs = $this->call(Verbs::GET, '/api/v2/system/environment', [], [], [], ['HTTP_ACCEPT' => '*/*']);
        $content = $rs->getContent();
        $this->assertContains('authentication', $content);
    }
}