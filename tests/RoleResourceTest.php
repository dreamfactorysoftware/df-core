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
use DreamFactory\Rave\Testing\TestServiceRequest;
use DreamFactory\Rave\Services\SystemManager;
use DreamFactory\Rave\Enums\ContentTypes;
use DreamFactory\Rave\Contracts\ServiceResponseInterface;
use DreamFactory\Rave\Enums\HttpStatusCodes;
use DreamFactory\Library\Utility\ArrayUtils;

class RoleResourceTest extends \DreamFactory\Rave\Testing\TestCase
{
    const RESOURCE = 'role';

    protected $role1 = [
        'name' => 'Test role 1',
        'description' => 'A test role',
        'is_active' => 1,
        'role_service_access_by_role_id' => [
            [
                'service_id' => 2,
                'component' => '*',
                'verb_mask' => 31,
                'requestor_mask' => 3
            ]
        ],
        'role_lookup_by_role_id' => [
            [
                'name' => 'test1_1',
                'value' => '1231',
                'private' => 0
            ],
            [
                'name' => 'test2_1',
                'value' => '12341',
                'private' => 1
            ]
        ]

    ];

    protected $role2 = [
        'name' => 'Test role 2',
        'description' => 'A test role',
        'is_active' => 1,
        'role_service_access_by_role_id' => [
            [
                'service_id' => 2,
                'component' => '*',
                'verb_mask' => 31,
                'requestor_mask' => 3
            ]
        ],
        'role_lookup_by_role_id' => [
            [
                'name' => 'test1_2',
                'value' => '1232',
                'private' => 0
            ],
            [
                'name' => 'test2_2',
                'value' => '12342',
                'private' => 1
            ]
        ]
    ];

    protected $role3 = [
        'name' => 'Test role 3',
        'description' => 'A test role',
        'is_active' => 1,
        'role_service_access_by_role_id' => [
            [
                'service_id' => 2,
                'component' => '*',
                'verb_mask' => 31,
                'requestor_mask' => 3
            ]
        ],
        'role_lookup_by_role_id' => [
            [
                'name' => 'test1_3',
                'value' => '1233',
                'private' => 0
            ],
            [
                'name' => 'test2_3',
                'value' => '12343',
                'private' => 1
            ]
        ]
    ];

    protected static $roleIds = [];

    protected static $staged = false;

    /** @var SystemManager null  */
    protected static $service = null;

    public function stage()
    {
        parent::stage();

        $settings = [
            'name'        => 'system',
            'label'       => 'System Manager',
            'description' => 'Handles all system resources and configuration'
        ];

        static::$service = new SystemManager($settings);
    }

    public function testPOSTCreateRoles()
    {
        $request = $this->makeRequest(Verbs::POST, [], [], [$this->role1, $this->role2, $this->role3]);
        /** @var ServiceResponseInterface $response */
        $response = static::$service->handleRequest($request, self::RESOURCE);
        $content = $response->getContent();

        $this->assertTrue(isset($content['record']));

        $records = ArrayUtils::get($content, 'record');

        static::$roleIds = [];
        foreach($records as $r)
        {
            $this->assertTrue(ArrayUtils::get($r, 'id')>0);
            static::$roleIds[] = ArrayUtils::get($r, 'id');
        }

        $this->assertEquals(HttpStatusCodes::HTTP_CREATED, $response->getStatusCode());
    }

    public function testPATCHRole()
    {
        $role1 = $this->getRole(static::$roleIds[0]);

        $role1['name'] = 'Patched Test Role 1';
        $role1['role_service_access_by_role_id'][0]['component'] = 'test';
        $role1['role_lookup_by_role_id'][0]['name'] = 'patched-test1_1';
        $role1['role_lookup_by_role_id'][0]['value'] = '897';

        $patchRequest = $this->makeRequest(Verbs::PATCH, [], [], [$role1]);
        $rs = static::$service->handleRequest($patchRequest, self::RESOURCE);
        $c = $rs->getContent();

        $this->assertTrue(ArrayUtils::get($c, 'id')===static::$roleIds[0]);

        $updatedRole = $this->getRole(static::$roleIds[0]);

        $this->assertEquals('Patched Test Role 1', $updatedRole['name']);
        $this->assertEquals('test', $updatedRole['role_service_access_by_role_id'][0]['component']);
        $this->assertEquals('patched-test1_1', $updatedRole['role_lookup_by_role_id'][0]['name']);
        $this->assertEquals('897', $updatedRole['role_lookup_by_role_id'][0]['value']);
    }

    public function testPATCHCreateRelation()
    {
        $role2 = $this->getRole(static::$roleIds[1]);

        unset($role2['role_lookup_by_role_id'][0]['id']);

        $patchRequest = $this->makeRequest(Verbs::PATCH, [], [], [$role2]);
        $rs = static::$service->handleRequest($patchRequest, self::RESOURCE);
        $c = $rs->getContent();

        $this->assertTrue(ArrayUtils::get($c, 'id')===static::$roleIds[1]);

        $updatedRole = $this->getRole(static::$roleIds[1]);

        $this->assertEquals(3, count($updatedRole['role_lookup_by_role_id']));
    }

    public function testPATCHDeleteRelation()
    {
        $role2 = $this->getRole(static::$roleIds[1]);

        $role2['role_lookup_by_role_id'][0]['role_id'] = null;

        $patchRequest = $this->makeRequest(Verbs::PATCH, [], [], [$role2]);
        $rs = static::$service->handleRequest($patchRequest, self::RESOURCE);
        $c = $rs->getContent();

        $this->assertTrue(ArrayUtils::get($c, 'id')===static::$roleIds[1]);

        $updatedRole = $this->getRole(static::$roleIds[1]);

        $this->assertEquals(2, count($updatedRole['role_lookup_by_role_id']));
    }

    public function testPATCHAdoptRelation()
    {
        $role1 = $this->getRole(static::$roleIds[0]);
        $role2 = $this->getRole(static::$roleIds[1]);

        $role2['role_lookup_by_role_id'][0]['role_id'] = $role1['role_lookup_by_role_id'][0]['role_id'];

        $patchRequest = $this->makeRequest(Verbs::PATCH, [], [], [$role2]);
        $rs = static::$service->handleRequest($patchRequest, self::RESOURCE);
        $c = $rs->getContent();

        $this->assertTrue(ArrayUtils::get($c, 'id')===static::$roleIds[1]);

        $updatedRole = $this->getRole(static::$roleIds[1]);
        $otherRole = $this->getRole(static::$roleIds[0]);

        $this->assertEquals(1, count($updatedRole['role_lookup_by_role_id']));
        $this->assertEquals(3, count($otherRole['role_lookup_by_role_id']));
    }

    public function testPATCHMultipleRoles()
    {
        $role1 = $this->getRole(static::$roleIds[0]);
        $role2 = $this->getRole(static::$roleIds[1]);
        $role3 = $this->getRole(static::$roleIds[2]);

        $role1['role_lookup_by_role_id'][0]['role_id'] = $role2['role_lookup_by_role_id'][0]['role_id'];

        $role1['name'] = 'test-multiple-update_1';
        $role1['role_service_access_by_role_id'][0]['component'] = 'updated1';
        $role1['role_lookup_by_role_id'][1]['name'] = 'test-updated-1';

        $role2['name'] = 'test-multiple-update_2';
        $role2['role_service_access_by_role_id'][0]['component'] = 'updated2';
        $role2['role_lookup_by_role_id'][1]['name'] = 'test-updated-2';

        $role3['name'] = 'test-multiple-update_3';
        $role3['role_service_access_by_role_id'][0]['component'] = 'updated3';
        $role3['role_lookup_by_role_id'][1]['name'] = 'test-updated-3';

        $patchRequest = $this->makeRequest(Verbs::PATCH, [], [], [$role1, $role2, $role3]);
        $rs = static::$service->handleRequest($patchRequest, self::RESOURCE);
        $c = $rs->getContent();
        $records = ArrayUtils::get($c, 'record');

        foreach($records as $key => $record)
        {
            $this->assertEquals(static::$roleIds[$key], ArrayUtils::get($record, 'id'));
        }

    }

    public function testDELETERoles()
    {
        $ids = implode(',', static::$roleIds);
        $request = $this->makeRequest(Verbs::DELETE, ['ids'=>$ids]);
        $response = static::$service->handleRequest($request, self::RESOURCE);

        $content = $response->getContent();
        $records = ArrayUtils::get($content, 'record');

        foreach($records as $key => $record)
        {
            $this->assertEquals(static::$roleIds[$key], ArrayUtils::get($record, 'id'));
        }

        static::$roleIds = [];
    }

    protected function getRole($id=null)
    {
        if(!empty($id))
        {
            $resource = self::RESOURCE.'/'.$id;
        }
        else
        {
            $resource = self::RESOURCE;
        }

        $getRequest = $this->makeRequest(Verbs::GET, ['related' => 'role_lookup_by_role_id,role_service_access_by_role_id']);
        $getResponse = static::$service->handleRequest($getRequest, $resource);
        $role = $getResponse->getContent();

        return $role;
    }

    protected function makeRequest($verb, $query=[], $header=[], $payload=null)
    {
        $request = new TestServiceRequest($verb, $query, $header);
        $request->setApiVersion('v1');

        if(!empty($payload))
        {
            if(is_array($payload))
            {
                $request->setContent($payload);
            }
            else
            {
                $request->setContent($payload, ContentTypes::JSON);
            }
        }

        return $request;
    }
}