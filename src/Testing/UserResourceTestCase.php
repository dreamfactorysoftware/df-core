<?php

namespace DreamFactory\Core\Testing;

use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Models\User;
use DreamFactory\Library\Utility\Enums\Verbs;
use DreamFactory\Core\Utility\Session;
use Illuminate\Support\Arr;
use Hash;

class UserResourceTestCase extends TestCase
{
    const RESOURCE = 'foo';

    protected $serviceId = 'system';

    protected $user1 = [
        'name'              => 'John Doe',
        'first_name'        => 'John',
        'last_name'         => 'Doe',
        'email'             => 'jdoe@dreamfactory.com',
        'password'          => 'test1234',
        'security_question' => 'Make of your first car?',
        'security_answer'   => 'mazda',
        'is_active'         => true
    ];

    protected $user2 = [
        'name'                   => 'Jane Doe',
        'first_name'             => 'Jane',
        'last_name'              => 'Doe',
        'email'                  => 'jadoe@dreamfactory.com',
        'password'               => 'test1234',
        'is_active'              => true,
        'user_lookup_by_user_id' => [
            [
                'name'    => 'test',
                'value'   => '1234',
                'private' => false
            ],
            [
                'name'    => 'test2',
                'value'   => '5678',
                'private' => true
            ]
        ]
    ];

    protected $user3 = [
        'name'                   => 'Dan Doe',
        'first_name'             => 'Dan',
        'last_name'              => 'Doe',
        'email'                  => 'ddoe@dreamfactory.com',
        'password'               => 'test1234',
        'is_active'              => true,
        'user_lookup_by_user_id' => [
            [
                'name'    => 'test',
                'value'   => '1234',
                'private' => false
            ],
            [
                'name'    => 'test2',
                'value'   => '5678',
                'private' => true
            ],
            [
                'name'    => 'test3',
                'value'   => '56789',
                'private' => true
            ]
        ]
    ];

    public function tearDown()
    {
        $this->deleteUser(1);
        $this->deleteUser(2);
        $this->deleteUser(3);

        parent::tearDown();
    }

    /************************************************
     * Testing POST
     ************************************************/
    public function testPOSTCreateAdmins()
    {
        $payload = json_encode([$this->user1, $this->user2], JSON_UNESCAPED_SLASHES);

        $rs =
            $this->makeRequest(Verbs::POST, static::RESOURCE,
                [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'],
                $payload);
        $content = $rs->getContent();
        $data = Arr::get($content, static::$wrapper);

        $this->assertEquals(Arr::get($this->user1, 'email'), Arr::get($data, '0.email'));
        $this->assertEquals(Arr::get($this->user2, 'email'), Arr::get($data, '1.email'));
        $this->assertEquals(0, count(Arr::get($data, '0.user_lookup_by_user_id')));
        $this->assertEquals(2, count(Arr::get($data, '1.user_lookup_by_user_id')));
        $this->assertTrue($this->adminCheck($data));
    }

    public function testPOSTCreateAdmin()
    {
        $payload = json_encode([$this->user3], JSON_UNESCAPED_SLASHES);

        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE,
            [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'],
            $payload
        );
        $data = $rs->getContent();

        $this->assertEquals(Arr::get($this->user3, 'email'), Arr::get($data, static::$wrapper . '.0.email'));
        $this->assertEquals(3, count(Arr::get($data, static::$wrapper . '.0.user_lookup_by_user_id')));
        $this->assertEquals('**********', Arr::get($data, static::$wrapper . '.0.user_lookup_by_user_id.1.value'));
        $this->assertEquals('**********', Arr::get($data, static::$wrapper . '.0.user_lookup_by_user_id.2.value'));
        $this->assertTrue($this->adminCheck($data));
    }

    /************************************************
     * Testing PATCH
     ************************************************/

    public function testPATCHById()
    {
        $user = $this->createUser(1);

        $data = [
            'name'                   => 'Julie Doe',
            'first_name'             => 'Julie',
            'user_lookup_by_user_id' => [
                [
                    'name'  => 'param1',
                    'value' => '1234'
                ]
            ]
        ];

        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);

        $rs =
            $this->makeRequest(Verbs::PATCH, static::RESOURCE . '/' . $user['id'],
                [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'], $payload);
        $content = $rs->getContent();

        $this->assertEquals('Julie Doe', $content['name']);
        $this->assertEquals('Julie', $content['first_name']);
        $this->assertEquals('param1', Arr::get($content, 'user_lookup_by_user_id.0.name'));
        $this->assertEquals('1234', Arr::get($content, 'user_lookup_by_user_id.0.value'));

        Arr::set($content, 'user_lookup_by_user_id.0.name', 'my_param');
        Arr::set($content, 'user_lookup_by_user_id.1', ['name' => 'param2', 'value' => 'secret', 'private' => true]);

        $rs = $this->makeRequest(
            Verbs::PATCH,
            static::RESOURCE . '/' . $user['id'],
            [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'],
            json_encode($content, JSON_UNESCAPED_SLASHES)
        );

        $content = $rs->getContent();

        $this->assertEquals('my_param', Arr::get($content, 'user_lookup_by_user_id.0.name'));
        $this->assertEquals('**********', Arr::get($content, 'user_lookup_by_user_id.1.value'));
        $this->assertEquals(1, Arr::get($content, 'user_lookup_by_user_id.1.private'));
        $this->assertTrue($this->adminCheck([$content]));
    }

    public function testPATCHByIds()
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);
        $user3 = $this->createUser(3);

        $payload = json_encode(
            [
                [
                    'is_active'              => false,
                    'user_lookup_by_user_id' => [
                        [
                            'name'  => 'common',
                            'value' => 'common name'
                        ]
                    ]
                ]
            ],
            JSON_UNESCAPED_SLASHES
        );

        $ids = implode(',', array_column([$user1, $user2, $user3], 'id'));
        $rs =
            $this->makeRequest(Verbs::PATCH, static::RESOURCE,
                [ApiOptions::IDS => $ids, ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'],
                $payload);
        $content = $rs->getContent();
        $data = $content[static::$wrapper];

        foreach ($data as $user) {
            $this->assertEquals(0, $user['is_active']);
        }

        $this->assertEquals('common name', Arr::get($data, '0.user_lookup_by_user_id.0.value'));
        $this->assertEquals('common name', Arr::get($data, '1.user_lookup_by_user_id.2.value'));
        $this->assertEquals('common name', Arr::get($data, '2.user_lookup_by_user_id.3.value'));
        $this->assertTrue($this->adminCheck($data));
    }

    public function testPATCHByRecords()
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);
        $user3 = $this->createUser(3);

        Arr::set($user1, 'first_name', 'Kevin');
        Arr::set($user2, 'first_name', 'Lloyed');
        Arr::set($user3, 'first_name', 'Jack');

        $payload = json_encode([$user1, $user2, $user3], JSON_UNESCAPED_SLASHES);

        $rs =
            $this->makeRequest(Verbs::PATCH, static::RESOURCE,
                [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'],
                $payload);
        $content = $rs->getContent();

        $this->assertEquals($user1['first_name'], Arr::get($content, static::$wrapper . '.0.first_name'));
        $this->assertEquals($user2['first_name'], Arr::get($content, static::$wrapper . '.1.first_name'));
        $this->assertEquals($user3['first_name'], Arr::get($content, static::$wrapper . '.2.first_name'));
        $this->assertTrue($this->adminCheck($content[static::$wrapper]));
    }

    public function testPATCHPassword()
    {
        $user = $this->createUser(1);

        Arr::set($user, 'password', '1234');

        $payload = json_encode($user, JSON_UNESCAPED_SLASHES);
        $rs = $this->makeRequest(Verbs::PATCH, static::RESOURCE . '/' . $user['id'], [], $payload);
        $content = $rs->getContent();

        $this->assertTrue(Session::authenticate(['email' => $user['email'], 'password' => '1234']));
        $this->assertTrue($this->adminCheck([$content]));
    }

    public function testPATCHSecurityAnswer()
    {
        $user = $this->createUser(1);

        Arr::set($user, 'security_answer', 'mazda');

        $payload = json_encode($user, JSON_UNESCAPED_SLASHES);
        $rs =
            $this->makeRequest(Verbs::PATCH, static::RESOURCE . '/' . $user['id'],
                [ApiOptions::FIELDS => 'id,security_answer'],
                $payload);
        $content = $rs->getContent();

        $this->assertTrue($this->adminCheck([$content]));
        $this->assertTrue(Hash::check('mazda', $content['security_answer']));
    }

    /************************************************
     * Testing GET
     ************************************************/

    public function testGET()
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);
        $user3 = $this->createUser(3);

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE);
        $content = $rs->getContent();

        //Total 4 users including the default admin user that was seeded by the seeder.
        $this->assertEquals(4, count($content[static::$wrapper]));
        $this->assertTrue($this->adminCheck($content[static::$wrapper]));

        $ids = implode(',', array_column($content[static::$wrapper], 'id'));
        $this->assertEquals(implode(',', array_column([['id' => 1], $user1, $user2, $user3], 'id')), $ids);
    }

    public function testGETById()
    {
        $user = $this->createUser(2);

        $rs = $this->makeRequest(
            Verbs::GET,
            static::RESOURCE . '/' . $user['id'],
            [ApiOptions::RELATED => 'user_lookup_by_user_id']
        );
        $data = $rs->getContent();

        $this->assertEquals($user['name'], $data['name']);
        $this->assertTrue($this->adminCheck([$data]));

        $this->assertTrue($this->adminCheck([$data]));
        $this->assertEquals(count($user['user_lookup_by_user_id']), count($data['user_lookup_by_user_id']));
    }

    public function testGETByIds()
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);
        $user3 = $this->createUser(3);

        $ids = implode(',', array_column([$user1, $user2, $user3], 'id'));
        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE, [ApiOptions::IDS => $ids]);
        $content = $rs->getContent();

        //Total 4 users including the default admin user that was seeded by the seeder.
        $this->assertEquals(3, count($content[static::$wrapper]));

        $idsOut = implode(',', array_column($content[static::$wrapper], 'id'));
        $this->assertEquals($ids, $idsOut);
        $this->assertTrue($this->adminCheck($content[static::$wrapper]));
    }

    public function testGETByRecord()
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);
        $user3 = $this->createUser(3);

        $payload = json_encode([$user1, $user2, $user3], JSON_UNESCAPED_SLASHES);

        $ids = implode(',', array_column([$user1, $user2, $user3], 'id'));
        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE, [], $payload);
        $content = $rs->getContent();

        //Total 4 users including the default admin user that was seeded by the seeder.
        $this->assertEquals(3, count($content[static::$wrapper]));

        $idsOut = implode(',', array_column($content[static::$wrapper], 'id'));
        $this->assertEquals($ids, $idsOut);
        $this->assertTrue($this->adminCheck($content[static::$wrapper]));
    }

    public function testGETByFilterFirstNameLastName()
    {
        $this->createUser(1);
        $this->createUser(2);
        $this->createUser(3);

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE, [ApiOptions::FILTER => "first_name='Dan'"]);
        $content = $rs->getContent();
        $data = $content[static::$wrapper];
        $firstNames = array_column($data, 'first_name');

        $this->assertTrue(in_array('Dan', $firstNames));
        $this->assertEquals(1, count($data));

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE, [ApiOptions::FILTER => "last_name='doe'"]);
        $content = $rs->getContent();
        $data = $content[static::$wrapper];
        $firstNames = array_column($data, 'first_name');
        $lastNames = array_column($data, 'last_name');

        $this->assertTrue(in_array('Dan', $firstNames));
        $this->assertTrue(in_array('Doe', $lastNames));
        $this->assertEquals(3, count($data));
        $this->assertTrue($this->adminCheck($content[static::$wrapper]));
    }

    public function testGETWithLimitOffset()
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);
        $user3 = $this->createUser(3);

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE, [ApiOptions::LIMIT => 3]);
        $content = $rs->getContent();

        $this->assertEquals(3, count($content[static::$wrapper]));

        $idsOut = implode(',', array_column($content[static::$wrapper], 'id'));
        $this->assertEquals(implode(',', array_column([['id' => 1], $user1, $user2], 'id')), $idsOut);

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE, [ApiOptions::LIMIT => 3, ApiOptions::OFFSET => 1]);
        $content = $rs->getContent();

        $this->assertEquals(3, count($content[static::$wrapper]));

        $idsOut = implode(',', array_column($content[static::$wrapper], 'id'));
        $this->assertEquals(implode(',', array_column([$user1, $user2, $user3], 'id')), $idsOut);

        $rs = $this->makeRequest(Verbs::GET, static::RESOURCE, [ApiOptions::LIMIT => 2, ApiOptions::OFFSET => 2]);
        $content = $rs->getContent();

        $this->assertEquals(2, count($content[static::$wrapper]));

        $idsOut = implode(',', array_column($content[static::$wrapper], 'id'));
        $this->assertEquals(implode(',', array_column([$user2, $user3], 'id')), $idsOut);
        $this->assertTrue($this->adminCheck($content[static::$wrapper]));
    }

    public function testGETWithOrder()
    {
        $user1 = $this->createUser(1);
        $user2 = $this->createUser(2);
        $user3 = $this->createUser(3);

        $ids = implode(',', array_column([$user1, $user2, $user3], 'id'));
        $rs =
            $this->makeRequest(Verbs::GET, static::RESOURCE,
                [ApiOptions::IDS => $ids, ApiOptions::ORDER => 'first_name']);
        $content = $rs->getContent();
        $data = $content[static::$wrapper];
        $firstNames = implode(',', array_column($data, 'first_name'));

        $this->assertEquals('Dan,Jane,John', $firstNames);

        $rs =
            $this->makeRequest(Verbs::GET, static::RESOURCE,
                [ApiOptions::IDS => $ids, ApiOptions::ORDER => 'first_name DESC']);
        $content = $rs->getContent();
        $data = $content[static::$wrapper];
        $firstNames = implode(',', array_column($data, 'first_name'));

        $this->assertEquals('John,Jane,Dan', $firstNames);

        $rs =
            $this->makeRequest(Verbs::GET, static::RESOURCE,
                [ApiOptions::IDS => $ids, ApiOptions::ORDER => 'last_name,first_name DESC']);
        $content = $rs->getContent();
        $data = $content[static::$wrapper];
        $firstNames = implode(',', array_column($data, 'first_name'));

        $this->assertEquals('John,Jane,Dan', $firstNames);
        $this->assertTrue($this->adminCheck($content[static::$wrapper]));
    }

    /************************************************
     * Helper methods
     ************************************************/

    protected function createUser($num)
    {
        $user = $this->{'user' . $num};
        $payload = json_encode([$user], JSON_UNESCAPED_SLASHES);
        $rs = $this->makeRequest(
            Verbs::POST,
            static::RESOURCE,
            [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'user_lookup_by_user_id'],
            $payload
        );

        $data = $rs->getContent();

        return Arr::get($data, static::$wrapper . '.0');
    }

    protected function deleteUser($num)
    {
        $user = $this->{'user' . $num};
        $email = Arr::get($user, 'email');
        User::whereEmail($email)->delete();
    }

    protected function adminCheck($records)
    {
        return false;
    }
}