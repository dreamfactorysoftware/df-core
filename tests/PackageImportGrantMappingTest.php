<?php

require_once __DIR__ . '/AzionImportTestCase.php';

use DreamFactory\Core\Models\App;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;

/**
 * Role grants (role_service_access) and user app roles whose service/app is not in the
 * package. getNewServiceId used to reuse the old id for any built-in service name it found
 * at that id on the target. 7.7 shifted the built-in ids by two, so a 4.x grant on db
 * (id 5) landed on files, and a grant on files (id 3) landed on system.
 */
class PackageImportGrantMappingTest extends AzionImportTestCase
{
    const ROLE = self::PREFIX . 'grant_role';

    public function testFourXGrantOnBuiltInNotInPackageIsSkippedWithWarning()
    {
        // 4.x ids: files = 3, db = 5. On 7.7 those ids are system and files.
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9101, static::ROLE, [
                $this->rsa(1, 9101, 3, '*'),
                $this->rsa(2, 9101, 5, '_table/*'),
            ])],
        ], '4.13.0');

        $importer = $this->import($zip);

        $this->assertNotEmpty(Role::whereName(static::ROLE)->value('id'), 'role not imported');
        $this->assertSame([], $this->grantServices(static::ROLE),
            '4.x grants on built-ins must not land on whatever service has that id in 7.7');
        $this->assertTrue($this->logged($importer, 'warning', 'Skipping relation role_service_access_by_role_id'));
    }

    public function testGrantOnServiceInPackageIsMappedByName()
    {
        $svc = static::PREFIX . 'grant_svc';
        $zip = $this->buildPackage([
            'system/service.json' => [$this->fileService($svc, 3)], // id collides with a 7.7 built-in
            'system/role.json'    => [$this->role(9102, static::ROLE, [$this->rsa(1, 9102, 3, 'a/*')])],
        ], '4.13.0');

        $this->import($zip);

        $this->assertSame([$svc . ':a/*'], $this->grantServices(static::ROLE));
    }

    public function testGrantWithNullServiceIdStaysNullAndIsNotSkipped()
    {
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9103, static::ROLE, [$this->rsa(1, 9103, null, '*', 31)])],
        ], '4.13.0');

        $this->import($zip);

        $grants = $this->grants(static::ROLE);
        $this->assertCount(1, $grants, 'grant with service_id null (all services) was dropped');
        $this->assertNull($grants[0]['service']);
        $this->assertEquals(31, $grants[0]['verb_mask']);
        $roleId = Role::whereName(static::ROLE)->value('id');
        $this->assertNull(\DB::table('role_service_access')->where('role_id', $roleId)->value('service_id'));
    }

    public function testPackageOmittingBuiltInsSkipsGrantsWithWarning()
    {
        // Even 7.7 -> 7.7: an upgraded source keeps older built-in ids, so a guess is unsafe.
        // A missing grant fails closed; a grant on the wrong service does not.
        $userId = Service::whereName('user')->value('id');
        $systemId = Service::whereName('system')->value('id');
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9104, static::ROLE, [
                $this->rsa(1, 9104, $userId, 'session'),
                $this->rsa(2, 9104, $systemId, 'environment'),
            ])],
        ], '7.7.0');

        $importer = $this->import($zip);

        $this->assertSame([], $this->grantServices(static::ROLE));
        $this->assertTrue($this->logged($importer, 'warning', 'Skipping relation role_service_access_by_role_id'));
    }

    public function testManifestWithoutDfVersionSkipsBuiltInGrant()
    {
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9105, static::ROLE, [$this->rsa(1, 9105, 9, 'session')])],
        ], '');

        $importer = $this->import($zip);

        $this->assertSame([], $this->grantServices(static::ROLE));
        $this->assertTrue($this->logged($importer, 'warning', 'Skipping relation role_service_access_by_role_id'));
    }

    /**
     * Fixed by never guessing built-in ids: a 7.0-7.6 export (or one from an
     * instance upgraded to 7.7) has user = 7. On a fresh 7.7 install id 7 is db, which is
     * also a built-in name, so a guess would map a 'user' grant onto 'db'.
     */
    public function testPre77SameMajorGrantDoesNotLandOnWrongBuiltIn()
    {
        $this->assertSame('db', Service::whereId(7)->value('name'), 'fixture assumes a fresh 7.7 id layout');
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9106, static::ROLE, [$this->rsa(1, 9106, 7, 'session')])],
        ], '7.6.0');

        $this->import($zip);

        $this->assertNotContains('db:session', $this->grantServices(static::ROLE),
            "a 7.6 grant on 'user' (id 7) landed on 'db', id 7 on a fresh 7.7 install");
    }

    public function testStringIdsInServiceJsonMatchIntGrantIds()
    {
        $svc = static::PREFIX . 'str_svc';
        $service = $this->fileService($svc, '9207');
        $zip = $this->buildPackage([
            'system/service.json' => [$service],
            'system/role.json'    => [$this->role(9107, static::ROLE, [$this->rsa(1, 9107, 9207, 'x')])],
        ], '4.13.0');

        $this->import($zip);

        $this->assertSame([$svc . ':x'], $this->grantServices(static::ROLE));
    }

    public function testIntIdsInServiceJsonMatchStringGrantIds()
    {
        $svc = static::PREFIX . 'str_svc2';
        $zip = $this->buildPackage([
            'system/service.json' => [$this->fileService($svc, 9208)],
            'system/role.json'    => [$this->role(9108, static::ROLE, [$this->rsa(1, 9108, '9208', 'y')])],
        ], '4.13.0');

        $this->import($zip);

        $this->assertSame([$svc . ':y'], $this->grantServices(static::ROLE));
    }

    /**
     * The built-in apps (admin, api_docs, file_manager) are seeded by AppSeeder, not by a
     * migration, so their ids (1, 2, 3) did not shift in 7.7. Base kept a 4.x user's role on
     * the admin app; gating getNewAppId on the same major version drops it.
     */
    public function testFourXUserAppRoleOnBuiltInAppIsKept()
    {
        $email = static::PREFIX . 'uar@azion.test';
        $adminAppId = App::whereName('admin')->value('id');
        $this->assertEquals(1, $adminAppId, 'fixture assumes admin app id 1');
        $zip = $this->buildPackage([
            'system/role.json' => [$this->role(9109, static::ROLE)],
            'system/user.json' => [[
                'id'                             => 9109,
                'name'                           => 'uar',
                'first_name'                     => 'u',
                'last_name'                      => 'ar',
                'email'                          => $email,
                'is_active'                      => true,
                'user_to_app_to_role_by_user_id' => [['id' => 1, 'user_id' => 9109, 'app_id' => 1, 'role_id' => 9109]],
            ]],
        ], '4.13.0');

        $this->import($zip);

        $userId = User::whereEmail($email)->value('id');
        $this->assertNotEmpty($userId, 'user not imported');
        $rows = \DB::table('user_to_app_to_role')->where('user_id', $userId)->get();
        $this->assertCount(1, $rows, "4.x user's role on the admin app was dropped");
        $this->assertEquals($adminAppId, $rows[0]->app_id);
        $this->assertEquals(Role::whereName(static::ROLE)->value('id'), $rows[0]->role_id);
    }
}
