<?php

use DreamFactory\Core\Utility\CacheUtilities;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\User;

class CacheUtilitiesTest extends \DreamFactory\Core\Testing\TestCase
{
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

    public function testRoleIdFromApiKey()
    {
        $app = App::firstOrFail();
        $roleId = CacheUtilities::getRoleIdByApiKeyAndUserId($app->api_key);

        $this->assertEquals(null, $roleId);
    }

    public function testRoleIdFromApiKeyUserId()
    {
        $app = App::firstOrFail();
        $roleId = CacheUtilities::getRoleIdByApiKeyAndUserId($app->api_key);

        $this->assertEquals(null, $roleId);
    }

    public function testAppInfo()
    {
        $app = App::firstOrFail();
        $info = CacheUtilities::getAppInfo($app->id);

        $this->assertEquals(null, $info);
    }

    public function testRoleInfo()
    {
        $role = Role::firstOrFail();
        $info = CacheUtilities::getRoleInfo($role->id);

        $this->assertEquals(null, $info);
    }

    public function testUserInfo()
    {
        $user = User::firstOrFail();
        $info = CacheUtilities::getUserInfo($user->id);

        $this->assertEquals(null, $info);
    }
}