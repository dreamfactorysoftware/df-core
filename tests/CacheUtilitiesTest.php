<?php

use DreamFactory\Core\Utility\CacheUtilities;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\User;

class CacheUtilitiesTest extends \DreamFactory\Core\Testing\TestCase
{
    public function tearDown()
    {
        User::whereEmail('john@dreamfactory.com')->delete();
        Role::whereName('test_role')->delete();
    }

    public function testAppIdFromApiKey()
    {
        $app = App::firstOrFail();
        $appId = CacheUtilities::getAppIdByApiKey($app->api_key);

        $this->assertEquals($app->id, $appId);
    }

    public function testApiKeyFromAppId()
    {
        $app = App::firstOrFail();
        $key = CacheUtilities::getApiKeyByAppId($app->id);

        $this->assertEquals($app->api_key, $key);
    }

    public function testRoleIdFromApiKeyUserId()
    {
        $app = App::firstOrFail();
        $role = Role::create(['name'=>'test_role', 'is_active'=>true]);

        $temp = [
            'name'              => 'John Doe',
            'first_name'        => 'John',
            'last_name'         => 'Doe',
            'email'             => 'john@dreamfactory.com',
            'password'          => 'test1234',
            'is_active'         => true
        ];

        /** @type User $user */
        $user = User::create($temp);
        \DreamFactory\Core\Models\UserAppRole::create(['user_id' => $user->id, 'app_id' => $app->id, 'role_id' => $role->id]);

        $roleId = CacheUtilities::getRoleIdByAppIdAndUserId($app->id, $user->id);

        $this->assertEquals($role->id, $roleId);
    }

    public function testAppInfo()
    {
        $app = App::firstOrFail();
        $info = CacheUtilities::getAppInfo($app->id);

        $this->assertNotEquals(null, $info);
    }

    public function testRoleInfo()
    {
        $role = Role::create(['name'=>'test_role', 'is_active'=>true]);
        $info = CacheUtilities::getRoleInfo($role->id);

        $this->assertNotEquals(null, $info);
    }

    public function testUserInfo()
    {
        $user = User::firstOrFail();
        $info = CacheUtilities::getUserInfo($user->id);

        $this->assertNotEquals(null, $info);
    }

    public function testServiceInfo()
    {
        $info = CacheUtilities::getServiceInfo('user');

        $this->assertNotEquals(null, $info);
    }
}