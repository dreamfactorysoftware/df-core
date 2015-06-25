<?php
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Utility\Session;
use DreamFactory\Core\Utility\JWTUtilities;
use Illuminate\Support\Arr;

class AccessCheckMiddlewareTest extends \DreamFactory\Core\Testing\TestCase
{
    public function tearDown()
    {
        User::whereEmail('jdoe@dreamfactory.com')->delete();
    }

    public function testSysAdmin()
    {
        $user = User::find(1);
        $token = JWTUtilities::makeJWTByUserId($user->id);

        $this->call(Verbs::GET, '/api/v2/system', [], [], [], ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);

        $this->assertTrue(Session::isSysAdmin(), 'assertion 1');
        $this->assertEquals(null, session('admin.role.id'), 'assertion 2');
        $adminLookup = session('lookup');
        $adminLookupSecret = session('lookup_secret');
        $this->assertTrue(isset($adminLookup), 'assertion 3');
        $this->assertTrue(isset($adminLookupSecret), 'assertion 4');
        $this->assertEquals(0, count($adminLookup), 'assertion 5');
        $this->assertEquals(0, count($adminLookupSecret), 'assertion 6');
        $rsa = session('role.services');
        $this->assertTrue(empty($rsa), 'assertion 7');
    }

    public function testApiKeyRole()
    {
        $app = App::find(1);
        $apiKey = $app->api_key;

        $this->call(Verbs::GET, '/api/v2/system', ['api_key' => $apiKey]);

        $this->assertFalse(Session::isSysAdmin());
        $this->assertEquals(1, Session::get('role.id'));
        $rsa = Session::get('role.services');
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

        \DreamFactory\Core\Models\UserAppRole::create(['user_id' => $userId, 'app_id' => 2, 'role_id' => 1]);
        $app = App::find(2);
        $apiKey = $app->api_key;

        $myUser = User::find($userId);
        $token = JWTUtilities::makeJWTByUserId($myUser->id);
        $this->call(
            Verbs::GET, '/api/v2/system',
            [],
            [],
            [],
            ['HTTP_X_DREAMFACTORY_API_KEY' => $apiKey, 'HTTP_X_DREAMFACTORY_SESSION_TOKEN' => $token]);

        $this->assertFalse(Session::isSysAdmin());
        $this->assertEquals(1, Session::get('role.id'));
        $rsa = Session::get('role.services');
        $this->assertTrue(!empty($rsa));
    }

    public function testPathException()
    {
        $rs = $this->call(Verbs::GET, '/api/v2/system/environment', [], [], [], ['HTTP_ACCEPT'=>'*/*']);
        $content = $rs->getContent();
        $this->assertContains('authentication', $content);
    }
}