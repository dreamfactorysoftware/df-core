<?php

require_once __DIR__ . '/AzionImportTestCase.php';

use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;

/**
 * Import with overwrite=true onto records that already exist.
 *
 * Before the fix, patchExisting PATCHed nested relations (role_service_access_by_role_id,
 * user_to_app_to_role_by_user_id) with the source instance's row ids and foreign keys,
 * which rewrote unrelated existing rows. It also copied the package password hash onto
 * existing admins/users, and PATCHed scalar foreign keys (app.role_id) with source ids.
 */
class PackageImportOverwriteTest extends AzionImportTestCase
{
    protected function createFileService($name)
    {
        return $this->unguarded(Service::class, [
            'name'      => $name,
            'label'     => $name,
            'type'      => 'local_file',
            'is_active' => true,
            'config'    => ['container' => 'app'],
        ]);
    }

    protected function createUser($email, $password, $admin = false)
    {
        $user = $this->unguarded(User::class, [
            'name'         => $email,
            'first_name'   => 'az',
            'last_name'    => 'test',
            'email'        => $email,
            'password'     => $password,
            'is_active'    => true,
            'is_sys_admin' => $admin,
        ]);

        return $user;
    }

    protected function userRecord($id, $email, $passwordHash, array $uar = [])
    {
        return [
            'id'                             => $id,
            'name'                           => 'pkg ' . $email,
            'first_name'                     => 'pkg',
            'last_name'                      => 'user',
            'email'                          => $email,
            'is_active'                      => true,
            'password'                       => $passwordHash,
            'created_by_id'                  => 9999,
            'last_modified_by_id'            => 9999,
            'user_to_app_to_role_by_user_id' => $uar,
        ];
    }

    protected function appRecord($id, $name, $roleId, $storageServiceId = null)
    {
        return [
            'id'                  => $id,
            'name'                => $name,
            'api_key'             => hash('sha256', $name . microtime()),
            'description'         => 'pkg ' . $name,
            'is_active'           => true,
            'type'                => 0,
            'role_id'             => $roleId,
            'storage_service_id'  => $storageServiceId,
            'created_by_id'       => 9999,
            'last_modified_by_id' => 9999,
        ];
    }

    // ---------------------------------------------------------------- roles (bug 3)

    public function testOverwriteRoleKeepsExistingGrantAndAddsMappedOne()
    {
        $role = static::PREFIX . 'ow_role';
        $svcA = $this->createFileService(static::PREFIX . 'ow_a');
        $svcB = static::PREFIX . 'ow_b';
        $this->createRole($role, [['service_id' => $svcA->id, 'component' => 'a/*']]);
        $existingRowId = \DB::table('role_service_access')->where('service_id', $svcA->id)->value('id');
        $systemId = Service::whereName('system')->value('id');

        // Source ids collide with target ids: the access row id is the existing row's id,
        // and svcB's source id is the target's system service id.
        $zip = $this->buildPackage([
            'system/service.json' => [$this->fileService($svcB, $systemId)],
            'system/role.json'    => [$this->role(9501, $role, [$this->rsa($existingRowId, 9501, $systemId, 'b/*')])],
        ], '4.13.0');

        $this->import($zip, true);
        $this->assertSame([static::PREFIX . 'ow_a:a/*', $svcB . ':b/*'], $this->grantServices($role));

        // Re-importing the same package must not add duplicates.
        $this->import($zip, true);
        $this->assertSame([static::PREFIX . 'ow_a:a/*', $svcB . ':b/*'], $this->grantServices($role));
    }

    public function testOverwriteRoleStillAppliesLookups()
    {
        $role = static::PREFIX . 'ow_lookup_role';
        $this->createRole($role);
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9502, $role, [], [
                'description'       => 'updated by package',
                'lookup_by_role_id' => [['name' => 'az_k1', 'value' => 'v1', 'private' => false]],
            ])],
        ]);

        $this->import($zip, true);

        $roleId = Role::whereName($role)->value('id');
        $this->assertEquals('updated by package', Role::find($roleId)->description);
        $lookup = \DreamFactory\Core\Models\RoleLookup::where('role_id', $roleId)->where('name', 'az_k1')->first();
        $this->assertNotNull($lookup, 'lookup_by_role_id was not applied on overwrite');
        $this->assertEquals('v1', $lookup->value);
    }

    /**
     * A source lookup id that matches another role's lookup row on the target.
     * Pre-existing behavior (base and fix both PATCH lookups with their ids).
     */
    public function testOverwriteRoleLookupWithCollidingIdDoesNotTouchOtherRole()
    {
        $other = $this->createRole(static::PREFIX . 'ow_other_role');
        $otherLookupId = \DB::table('lookup')->insertGetId([
            'role_id' => $other->id, 'name' => 'az_other', 'value' => 'keep', 'private' => 0,
        ]);
        $role = static::PREFIX . 'ow_lookup_role2';
        $this->createRole($role);
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9503, $role, [], [
                'lookup_by_role_id' => [['id' => $otherLookupId, 'role_id' => 9503, 'name' => 'az_k2', 'value' => 'v2', 'private' => false]],
            ])],
        ]);

        try {
            $this->import($zip, true);
        } catch (\Exception $e) {
            // An error is acceptable; corrupting the other role is not.
        }

        $row = \DB::table('lookup')->where('id', $otherLookupId)->first();
        $this->assertNotNull($row, "other role's lookup row was deleted");
        $this->assertEquals($other->id, $row->role_id, "other role's lookup row was re-parented");
        $this->assertEquals('az_other', $row->name, "other role's lookup row was rewritten");
    }

    public function testOverwriteRoleStillAppliesRoleAdldap()
    {
        if (!\Schema::hasTable('role_adldap')) {
            $this->markTestSkipped('df-adldap not installed');
        }
        $role = static::PREFIX . 'ow_ldap_role';
        $this->createRole($role);

        // As exported (has-one, an object): the relation row carries the source role id.
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9505, $role, [], [
                'role_adldap_by_role_id' => ['role_id' => 9505, 'dn' => 'CN=mine,DC=azion'],
            ])],
        ]);

        $this->import($zip, true);

        $roleId = Role::whereName($role)->value('id');
        $this->assertEquals('CN=mine,DC=azion', \DB::table('role_adldap')->where('role_id', $roleId)->value('dn'),
            'role_adldap_by_role_id was not applied on overwrite');
    }

    /**
     * The exported role_adldap row's role_id (its primary key) is the source role id. When
     * it matches another role on the target, the PATCH must not rewrite that role's group.
     */
    public function testOverwriteRoleAdldapWithCollidingRoleIdDoesNotTouchOtherRole()
    {
        if (!\Schema::hasTable('role_adldap')) {
            $this->markTestSkipped('df-adldap not installed');
        }
        $role = static::PREFIX . 'ow_ldap_role2';
        $other = $this->createRole(static::PREFIX . 'ow_ldap_other');
        \DB::table('role_adldap')->insert(['role_id' => $other->id, 'dn' => 'CN=other,DC=azion']);
        $this->createRole($role);
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role($other->id, $role, [], [
                'role_adldap_by_role_id' => ['role_id' => $other->id, 'dn' => 'CN=mine,DC=azion'],
            ])],
        ]);

        try {
            $this->import($zip, true);
        } catch (\Exception $e) {
            // An error is acceptable; rewriting the other role is not.
        }

        $this->assertEquals('CN=other,DC=azion', \DB::table('role_adldap')->where('role_id', $other->id)->value('dn'),
            "other role's AD group was rewritten");
    }

    public function testOverwriteRoleWithRelationUnknownToTargetSucceeds()
    {
        // A source with an extra package (e.g. df-adldap) exports a relation the target lacks.
        $role = static::PREFIX . 'ow_unknown_rel';
        $created = $this->createRole($role);
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9506, $role, [], [
                'description'           => 'unknown rel',
                'role_nope_by_role_id'  => ['role_id' => 9506, 'x' => 'y'],
            ])],
        ]);

        $this->import($zip, true);

        $this->assertEquals('unknown rel', \DB::table('role')->where('id', $created->id)->value('description'));
    }

    public function testOverwriteRoleWithNullAdldapKeepsTargetGroup()
    {
        if (!\Schema::hasTable('role_adldap')) {
            $this->markTestSkipped('df-adldap not installed');
        }
        $role = static::PREFIX . 'ow_ldap_null';
        $created = $this->createRole($role);
        \DB::table('role_adldap')->insert(['role_id' => $created->id, 'dn' => 'CN=keep,DC=azion']);
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9507, $role, [], ['role_adldap_by_role_id' => null])],
        ]);

        $this->import($zip, true);

        $this->assertEquals('CN=keep,DC=azion', \DB::table('role_adldap')->where('role_id', $created->id)->value('dn'));
    }

    public function testOverwriteRoleWithSourceAuditIdsDoesNotFail()
    {
        $role = static::PREFIX . 'ow_audit_role';
        $created = $this->createRole($role);
        $createdBy = \DB::table('role')->where('id', $created->id)->value('created_by_id');
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9504, $role, [], [
                'description'         => 'audit ids',
                'created_by_id'       => 987654,
                'last_modified_by_id' => 987654,
            ])],
        ]);

        $this->import($zip, true);

        $row = \DB::table('role')->where('id', $created->id)->first();
        $this->assertEquals('audit ids', $row->description);
        $this->assertEquals($createdBy, $row->created_by_id, 'created_by_id was overwritten with a source user id');
        $this->assertNotEquals(987654, $row->last_modified_by_id);
    }

    // ---------------------------------------------------------------- users / admins (bug 4)

    public function testOverwriteUserKeepsTargetPassword()
    {
        $email = static::PREFIX . 'ow_user@azion.test';
        $this->createUser($email, 'TargetPass#1');
        $zip = $this->buildPackage([
            'system/user.json' => [$this->userRecord(9511, $email, bcrypt('PkgPass#1'))],
        ]);

        $this->import($zip, true);

        $user = User::whereEmail($email)->first();
        $this->assertEquals('pkg', $user->first_name, 'overwrite did not update the user');
        $this->assertTrue(\Hash::check('TargetPass#1', $user->password), 'overwrite replaced the target password');
        $this->assertFalse(\Hash::check('PkgPass#1', $user->password));
    }

    public function testOverwriteAdminKeepsTargetPassword()
    {
        $email = static::PREFIX . 'ow_admin@azion.test';
        $this->createUser($email, 'TargetAdmin#1', true);
        $record = $this->userRecord(9512, $email, bcrypt('PkgAdmin#1'));
        unset($record['user_to_app_to_role_by_user_id']);
        $record['is_sys_admin'] = true;
        $zip = $this->buildPackage([
            'system/admin.json' => [$record],
        ]);

        $this->import($zip, true);

        $user = User::whereEmail($email)->first();
        $this->assertTrue((bool)$user->is_sys_admin);
        $this->assertEquals('pkg', $user->first_name, 'overwrite did not update the admin');
        $this->assertTrue(\Hash::check('TargetAdmin#1', $user->password),
            'overwrite replaced the target admin password (install admin lockout)');
    }

    public function testNewUserStillGetsPackagePassword()
    {
        $email = static::PREFIX . 'new_user@azion.test';
        $zip = $this->buildPackage([
            'system/user.json' => [$this->userRecord(9513, $email, bcrypt('PkgPass#2'))],
        ]);

        $this->import($zip, true);

        $this->assertTrue(\Hash::check('PkgPass#2', User::whereEmail($email)->value('password')));
    }

    public function testOverwriteUserKeepsExistingAppRoleAndAddsMappedOne()
    {
        $email = static::PREFIX . 'ow_uar@azion.test';
        $roleA = $this->createRole(static::PREFIX . 'ow_uar_ra');
        $roleB = $this->createRole(static::PREFIX . 'ow_uar_rb');
        $appA = $this->unguarded(App::class, ['name' => static::PREFIX . 'ow_uar_appa', 'is_active' => true, 'type' => 0]);
        $appB = $this->unguarded(App::class, ['name' => static::PREFIX . 'ow_uar_appb', 'is_active' => true, 'type' => 0]);
        $user = $this->createUser($email, 'TargetPass#3');
        $existingUarId = \DB::table('user_to_app_to_role')->insertGetId([
            'user_id' => $user->id, 'app_id' => $appA->id, 'role_id' => $roleA->id,
        ]);

        // Source ids collide with unrelated target records: roleB's source id is roleC's
        // target id, appB's source id is the api_docs app's id, and the relation row id is
        // the existing row's id.
        $roleC = $this->createRole(static::PREFIX . 'ow_uar_rc');
        $apiDocsId = App::whereName('api_docs')->value('id');
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role($roleC->id, $roleB->name)],
            'system/app.json'  => [$this->appRecord($apiDocsId, $appB->name, $roleC->id)],
            'system/user.json' => [$this->userRecord(9514, $email, bcrypt('PkgPass#3'), [
                ['id' => $existingUarId, 'user_id' => 9514, 'app_id' => $apiDocsId, 'role_id' => $roleC->id],
            ])],
        ], '4.13.0');

        $this->import($zip, true);
        $this->import($zip, true);

        $rows = \DB::table('user_to_app_to_role')->where('user_id', $user->id)->orderBy('id')
            ->get(['app_id', 'role_id'])->map(function ($r) {
                return $r->app_id . ':' . $r->role_id;
            })->all();
        $this->assertSame(
            [$appA->id . ':' . $roleA->id, $appB->id . ':' . $roleB->id],
            $rows,
            'existing app role must stay, package app role must be added once, mapped by name'
        );
    }

    // ---------------------------------------------------------------- apps

    public function testOverwriteAppMapsRoleIdByName()
    {
        $roleA = $this->createRole(static::PREFIX . 'ow_app_ra');
        $roleB = $this->createRole(static::PREFIX . 'ow_app_rb');
        $app = static::PREFIX . 'ow_app';
        $this->unguarded(App::class, ['name' => $app, 'is_active' => true, 'type' => 0, 'role_id' => $roleA->id]);

        // Package: the app's role is roleB, whose source id equals roleA's target id.
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role($roleA->id, $roleB->name)],
            'system/app.json'  => [$this->appRecord(9521, $app, $roleA->id)],
        ], '4.13.0');

        $this->import($zip, true);

        $this->assertEquals($roleB->id, App::whereName($app)->value('role_id'),
            'app role_id was PATCHed with the source id instead of mapped by name');
    }

    public function testOverwriteAppWithUnmappedRoleKeepsExistingRole()
    {
        $roleA = $this->createRole(static::PREFIX . 'ow_app2_ra');
        $app = static::PREFIX . 'ow_app2';
        $this->unguarded(App::class, ['name' => $app, 'is_active' => true, 'type' => 0, 'role_id' => $roleA->id]);
        $zip = $this->buildPackage([
            'system/app.json' => [$this->appRecord(9522, $app, 987654)],
        ], '4.13.0');

        $this->import($zip, true);

        $this->assertEquals($roleA->id, App::whereName($app)->value('role_id'));
        $this->assertEquals('pkg ' . $app, App::whereName($app)->value('description'), 'app was not overwritten');
    }

    // ---------------------------------------------------------------- services / scripts

    public function testOverwriteServiceAddsServiceDoc()
    {
        $name = static::PREFIX . 'ow_doc0';
        $svc = $this->createFileService($name);
        $record = $this->fileService($name, 9530);
        $record['service_doc_by_service_id'] = ['service_id' => 9530, 'format' => 0, 'content' => '{"new":true}'];
        $zip = $this->buildPackage(['system/service.json' => [$record]]);

        $this->import($zip, true);

        $this->assertEquals('{"new":true}', \DB::table('service_doc')->where('service_id', $svc->id)->value('content'),
            'service_doc_by_service_id was not applied on overwrite');
    }

    /**
     * Pre-existing: when the target service already has a doc, the has-one PATCH inserts
     * instead of updating and the whole import fails on the service_doc primary key.
     */
    public function testOverwriteServiceUpdatesExistingServiceDoc()
    {
        $name = static::PREFIX . 'ow_doc';
        $svc = $this->createFileService($name);
        \DB::table('service_doc')->insert(['service_id' => $svc->id, 'format' => 0, 'content' => '{"old":true}']);
        $record = $this->fileService($name, 9531);
        $record['label'] = 'pkg label';
        $record['service_doc_by_service_id'] = ['service_id' => 9531, 'format' => 0, 'content' => '{"new":true}'];
        $zip = $this->buildPackage(['system/service.json' => [$record]]);

        $this->import($zip, true);

        $this->assertEquals('pkg label', Service::whereName($name)->value('label'));
        $this->assertEquals('{"new":true}', \DB::table('service_doc')->where('service_id', $svc->id)->value('content'),
            'service_doc_by_service_id was not applied on overwrite');
    }

    public function testOverwriteEventScriptUpdatesContent()
    {
        if (!class_exists(\DreamFactory\Core\Script\ServiceProvider::class)) {
            $this->markTestSkipped('df-script not installed');
        }
        $name = 'system.' . static::PREFIX . 'evs.get.pre_process';
        \DB::table('event_script')->insert([
            'name' => $name, 'type' => 'nodejs', 'is_active' => 0, 'content' => 'old', 'created_by_id' => 1,
        ]);
        $zip = $this->buildPackage([
            'system/event_script.json' => [[
                'name'                => $name,
                'type'                => 'nodejs',
                'is_active'           => false,
                'content'             => 'new content',
                'created_by_id'       => 987654,
                'last_modified_by_id' => 987654,
            ]],
        ]);

        $this->import($zip, true);

        $row = \DB::table('event_script')->where('name', $name)->first();
        $this->assertEquals('new content', $row->content);
        $this->assertEquals(1, $row->created_by_id);
    }
}
