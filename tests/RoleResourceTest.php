<?php
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Contracts\ServiceResponseInterface;
use DreamFactory\Core\Enums\ApiOptions;
use DreamFactory\Core\Enums\HttpStatusCodes;
use Illuminate\Support\Arr;

class RoleResourceTest extends \DreamFactory\Core\Testing\TestCase
{
    const RESOURCE = 'role';

    protected $role1 = [
        'name'                           => 'Test role 1',
        'description'                    => 'A test role',
        'is_active'                      => true,
        'role_service_access_by_role_id' => [
            [
                'service_id'     => 2,
                'component'      => '*',
                'verb_mask'      => 31,
                'requestor_mask' => 3
            ]
        ],
        'lookup_by_role_id'         => [
            [
                'name'    => 'test1_1',
                'value'   => '1231',
                'private' => false
            ],
            [
                'name'    => 'test2_1',
                'value'   => '12341',
                'private' => true
            ]
        ]

    ];

    protected $role2 = [
        'name'                           => 'Test role 2',
        'description'                    => 'A test role',
        'is_active'                      => true,
        'role_service_access_by_role_id' => [
            [
                'service_id'     => 2,
                'component'      => '*',
                'verb_mask'      => 31,
                'requestor_mask' => 3
            ]
        ],
        'lookup_by_role_id'         => [
            [
                'name'    => 'test1_2',
                'value'   => '1232',
                'private' => false
            ],
            [
                'name'    => 'test2_2',
                'value'   => '12342',
                'private' => true
            ]
        ]
    ];

    protected $role3 = [
        'name'                           => 'Test role 3',
        'description'                    => 'A test role',
        'is_active'                      => true,
        'role_service_access_by_role_id' => [
            [
                'service_id'     => 2,
                'component'      => '*',
                'verb_mask'      => 31,
                'requestor_mask' => 3
            ]
        ],
        'lookup_by_role_id'         => [
            [
                'name'    => 'test1_3',
                'value'   => '1233',
                'private' => false
            ],
            [
                'name'    => 'test2_3',
                'value'   => '12343',
                'private' => true
            ]
        ]
    ];

    protected static $roleIds = [];

    protected $serviceId = 'system';

    public function testPOSTCreateRoles()
    {
        /** @var ServiceResponseInterface $response */
        $response = $this->makeRequest(Verbs::POST, self::RESOURCE, [], [$this->role1, $this->role2]);
        $content = $response->getContent();

        $this->assertTrue(isset($content[static::$wrapper]));

        $records = array_get($content, static::$wrapper);

        static::$roleIds = [];

        foreach ($records as $r) {
            $this->assertTrue(array_get($r, 'id') > 0);
            static::$roleIds[] = array_get($r, 'id');
        }

        $this->assertEquals(HttpStatusCodes::HTTP_CREATED, $response->getStatusCode());
    }

    public function testPOSTCreateRolesWithFieldsAndRelated()
    {
        $response = $this->makeRequest(
            Verbs::POST,
            self::RESOURCE,
            [ApiOptions::FIELDS => 'name,id', ApiOptions::RELATED => 'lookup_by_role_id'],
            [$this->role3]
        );
        $content = $response->getContent();

        static::$roleIds[] = Arr::get($content, static::$wrapper . '.0.id');

        $this->assertEquals(array_get($this->role3, 'name'), Arr::get($content, static::$wrapper . '.0.name'));
        $this->assertEquals(
            Arr::get($this->role3, 'lookup_by_role_id.0.name'),
            Arr::get($content, static::$wrapper . '.0.lookup_by_role_id.0.name')
        );
    }

    public function testPATCHRole()
    {
        $role1 = $this->getRole(static::$roleIds[0]);

        $role1['name'] = 'Patched Test Role 1';
        $role1['role_service_access_by_role_id'][0]['component'] = 'test';
        $role1['lookup_by_role_id'][0]['name'] = 'patched-test1_1';
        $role1['lookup_by_role_id'][0]['value'] = '897';

        $rs = $this->makeRequest(Verbs::PATCH, self::RESOURCE, [], [$role1]);
        $c = $rs->getContent();

        $this->assertTrue(Arr::get($c, static::$wrapper . '.0.id') === static::$roleIds[0]);

        $updatedRole = $this->getRole(static::$roleIds[0]);

        $this->assertEquals('Patched Test Role 1', $updatedRole['name']);
        $this->assertEquals('test', $updatedRole['role_service_access_by_role_id'][0]['component']);
        $this->assertEquals('patched-test1_1', $updatedRole['lookup_by_role_id'][0]['name']);
        $this->assertEquals('897', $updatedRole['lookup_by_role_id'][0]['value']);
    }

    public function testPATCHRoleWithFieldsAndRelated()
    {
        $role1 = $this->getRole(static::$roleIds[0]);

        $role1['name'] = 'Patched Test Role 1_2';
        $role1['role_service_access_by_role_id'][0]['component'] = 'test_2';
        $role1['lookup_by_role_id'][0]['name'] = 'patched-test1_1_2';
        $role1['lookup_by_role_id'][0]['value'] = '900';

        $rs = $this->makeRequest(
            Verbs::PATCH,
            self::RESOURCE,
            [ApiOptions::FIELDS => 'id,name,is_active', ApiOptions::RELATED => 'role_service_access_by_role_id,lookup_by_role_id'],
            [$role1]
        );

        $c = $rs->getContent();
        $c = $c[static::$wrapper][0];

        $this->assertTrue(array_get($c, 'id') === static::$roleIds[0]);

        $updatedRole = $c;

        $this->assertEquals('Patched Test Role 1_2', $updatedRole['name']);
        $this->assertEquals('test_2', $updatedRole['role_service_access_by_role_id'][0]['component']);
        $this->assertEquals('patched-test1_1_2', $updatedRole['lookup_by_role_id'][0]['name']);
        $this->assertEquals('900', $updatedRole['lookup_by_role_id'][0]['value']);
    }

    public function testPATCHCreateRelation()
    {
        $role2 = $this->getRole(static::$roleIds[1]);

        unset($role2['lookup_by_role_id'][0]['id']);

        $rs = $this->makeRequest(Verbs::PATCH, self::RESOURCE, [], [$role2]);
        $c = $rs->getContent();

        $this->assertTrue(Arr::get($c, static::$wrapper . '.0.id') === static::$roleIds[1]);

        $updatedRole = $this->getRole(static::$roleIds[1]);

        $this->assertEquals(2, count($updatedRole['lookup_by_role_id']));
    }

    public function testPATCHDeleteRelation()
    {
        $role2 = $this->getRole(static::$roleIds[1]);

        $role2['lookup_by_role_id'][0]['role_id'] = null;

        $rs = $this->makeRequest(Verbs::PATCH, self::RESOURCE, [], [$role2]);
        $c = $rs->getContent();

        $this->assertTrue(Arr::get($c, static::$wrapper . '.0.id') === static::$roleIds[1]);

        $updatedRole = $this->getRole(static::$roleIds[1]);

        $this->assertEquals(1, count($updatedRole['lookup_by_role_id']));
    }

    public function testPATCHAdoptRelation()
    {
        $role1 = $this->getRole(static::$roleIds[0]);
        $role2 = $this->getRole(static::$roleIds[1]);

        $role2['lookup_by_role_id'][0]['role_id'] = $role1['lookup_by_role_id'][0]['role_id'];

        $rs = $this->makeRequest(Verbs::PATCH, self::RESOURCE, [], [$role2]);
        $c = $rs->getContent();

        $this->assertTrue(Arr::get($c, static::$wrapper . '.0.id') === static::$roleIds[1]);

        $updatedRole = $this->getRole(static::$roleIds[1]);
        $otherRole = $this->getRole(static::$roleIds[0]);

        $this->assertEquals(0, count($updatedRole['lookup_by_role_id']));
        $this->assertEquals(3, count($otherRole['lookup_by_role_id']));
    }

    public function testPATCHMultipleRoles()
    {
        $role1 = $this->getRole(static::$roleIds[0]);
        $role2 = $this->getRole(static::$roleIds[1]);
        $role3 = $this->getRole(static::$roleIds[2]);

        $role1['name'] = 'test-multiple-update_1';
        $role1['role_service_access_by_role_id'][0]['component'] = 'updated1';
        $role1['lookup_by_role_id'][1]['name'] = 'test-updated-1';

        $role2['name'] = 'test-multiple-update_2';
        $role2['role_service_access_by_role_id'][0]['component'] = 'updated2';
        $role2['lookup_by_role_id'][0] = $role1['lookup_by_role_id'][1];
        $role2['lookup_by_role_id'][0]['role_id']  = static::$roleIds[1];
        $role2['lookup_by_role_id'][0]['name'] = 'test-updated-2';

        $role3['name'] = 'test-multiple-update_3';
        $role3['role_service_access_by_role_id'][0]['component'] = 'updated3';
        $role3['lookup_by_role_id'][1]['name'] = 'test-updated-3';

        $rs = $this->makeRequest(
            Verbs::PATCH,
            self::RESOURCE,
            [ApiOptions::FIELDS => '*', ApiOptions::RELATED => 'role_service_access_by_role_id,lookup_by_role_id'],
            [$role1, $role2, $role3]
        );
        $c = $rs->getContent();
        $records = array_get($c, static::$wrapper);

        foreach ($records as $key => $record) {
            $this->assertEquals(static::$roleIds[$key], array_get($record, 'id'));
        }

        $this->assertEquals(Arr::get($role1, 'name'), Arr::get($records, '0.name'));
        $this->assertEquals(
            Arr::get($role1, 'role_service_access_by_role_id.0.component'),
            Arr::get($records, '0.role_service_access_by_role_id.0.component')
        );
        $this->assertEquals(Arr::get($role1, 'lookup_by_role_id.1.name'),
            Arr::get($records, '0.lookup_by_role_id.1.name'));

        $this->assertEquals(Arr::get($role2, 'name'), Arr::get($records, '1.name'));
        $this->assertEquals(
            Arr::get($role2, 'role_service_access_by_role_id.0.component'),
            Arr::get($records, '1.role_service_access_by_role_id.0.component')
        );
        $this->assertEquals(Arr::get($role2, 'lookup_by_role_id.0.name'),
            Arr::get($records, '1.lookup_by_role_id.0.name'));

        $this->assertEquals(Arr::get($role3, 'name'), Arr::get($records, '2.name'));
        $this->assertEquals(
            Arr::get($role3, 'role_service_access_by_role_id.0.component'),
            Arr::get($records, '2.role_service_access_by_role_id.0.component')
        );
        $this->assertEquals(Arr::get($role3, 'lookup_by_role_id.1.name'),
            Arr::get($records, '2.lookup_by_role_id.1.name'));
    }

    public function testDELETERoles()
    {
        $ids = implode(',', static::$roleIds);
        $response = $this->makeRequest(Verbs::DELETE, self::RESOURCE, [ApiOptions::IDS => $ids]);

        $content = $response->getContent();
        $records = array_get($content, static::$wrapper);

        foreach ($records as $key => $record) {
            $this->assertEquals(static::$roleIds[$key], array_get($record, 'id'));
        }

        static::$roleIds = [];
    }

    protected function getRole($id = null)
    {
        if (!empty($id)) {
            $resource = self::RESOURCE . '/' . $id;
        } else {
            $resource = self::RESOURCE;
        }

        $getResponse =
            $this->makeRequest(Verbs::GET, $resource,
                [ApiOptions::RELATED => 'lookup_by_role_id,role_service_access_by_role_id']);
        $role = $getResponse->getContent();

        return $role;
    }
}