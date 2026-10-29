<?php
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Session;
use Illuminate\Support\Facades\Hash;

class UserPasswordRehashTest extends \DreamFactory\Core\Testing\TestCase
{
    const EMAIL = 'rehash189@example.com';
    const USERNAME = 'rehash189';
    const PASSWORD = 'Secret123!';

    public function tearDown(): void
    {
        User::whereEmail(static::EMAIL)->delete();
        User::whereEmail('rehash189b@example.com')->delete();
        parent::tearDown();
    }

    protected function createUser($cost = null)
    {
        User::whereEmail(static::EMAIL)->delete();
        $user = User::create(
            ['name' => 'Rehash', 'email' => static::EMAIL, 'username' => static::USERNAME, 'is_active' => 1]
        );
        if (null !== $cost) {
            $user->updatePasswordHash(password_hash(static::PASSWORD, PASSWORD_BCRYPT, ['cost' => $cost]));
            $user->save();
        }

        return $user;
    }

    protected function login()
    {
        return Session::authenticate(['email' => static::EMAIL, 'password' => static::PASSWORD], false, true);
    }

    protected function assertLoginsKeepPasswordValid($cost)
    {
        $this->createUser($cost);

        $this->assertTrue($this->login());
        $this->assertTrue($this->login());

        $fresh = User::whereEmail(static::EMAIL)->first();
        $this->assertTrue(Hash::check(static::PASSWORD, $fresh->password));
        $this->assertFalse(Hash::needsRehash($fresh->password));
    }

    public function testFirstLoginWithLowerCostHashKeepsPasswordValid()
    {
        $this->assertLoginsKeepPasswordValid(10);
    }

    public function testFirstLoginWithHigherCostHashKeepsPasswordValid()
    {
        $this->assertLoginsKeepPasswordValid(13);
    }

    public function testRepeatedSaveDoesNotGrowUsernameUniqueRule()
    {
        $this->createUser();
        $user = User::whereEmail(static::EMAIL)->first();
        $user->save();
        $user->save();

        $rules = new ReflectionProperty($user, 'rules');
        $rules->setAccessible(true);
        $this->assertEquals(
            'min:6|unique:user,username|regex:/^\S*$/u|nullable',
            $rules->getValue($user)['username']
        );
    }

    public function testRehashOnLoginDoesNotLeakPasswordHashAttribute()
    {
        $this->createUser(10);
        $this->assertTrue($this->login());

        $fresh = User::whereEmail(static::EMAIL)->first();
        $this->assertArrayNotHasKey('password_hash', $fresh->toArray());
    }

    public function testSettingPasswordStillHashesOnce()
    {
        $user = $this->createUser();
        $user->password = static::PASSWORD;
        $user->save();

        $fresh = User::whereEmail(static::EMAIL)->first();
        $this->assertTrue(Hash::check(static::PASSWORD, $fresh->password));
    }

    public function testSettingHashLookingPasswordIsStillHashed()
    {
        $user = $this->createUser();
        $input = Hash::make('x');
        $user->password = $input;
        $user->save();

        $stored = User::whereEmail(static::EMAIL)->first()->password;
        $this->assertNotEquals($input, $stored);
        $this->assertTrue(Hash::check($input, $stored));
    }

    public function testUsernameUniquenessStillEnforcedOnUpdate()
    {
        $first = $this->createUser();
        $this->assertNotFalse($first->save());

        $second = User::create(
            ['name' => 'Rehash B', 'email' => 'rehash189b@example.com', 'username' => 'rehash189b', 'is_active' => 1]
        );
        $second->username = static::USERNAME;
        try {
            $saved = $second->save();
        } catch (\Exception $e) {
            $saved = false;
        }
        $this->assertFalse($saved);
    }
}
