<?php
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Utility\ServiceHandler;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Utility\Session;
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

        $this->be($user);
        Session::setUserInfo($user->toArray());
        $this->call(Verbs::GET, '/api/v2/system');

        $this->assertTrue(Session::isSysAdmin());
        $this->assertEquals(null, session('admin.role.id'));
        $adminLookup = session('admin.lookup');
        $adminLookupSecret = session('admin.lookup_secret');
        $this->assertTrue(isset($adminLookup));
        $this->assertTrue(isset($adminLookupSecret));
        $this->assertEquals(0, count($adminLookup));
        $this->assertEquals(0, count($adminLookupSecret));
        $rsa = session('admin.role.services');
        $this->assertTrue(empty($rsa));
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
        $this->be($myUser);
        Session::setUserInfo($myUser->toArray());
        $this->call(Verbs::GET, '/api/v2/system', [], [], [], ['HTTP_X_DREAMFACTORY_API_KEY' => $apiKey]);

        $this->assertFalse(Session::isSysAdmin());
        $this->assertEquals(1, Session::get('role.id'));
        $rsa = Session::get('role.services');
        $this->assertTrue(!empty($rsa));
    }
}