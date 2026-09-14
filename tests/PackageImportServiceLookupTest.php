<?php

use DreamFactory\Core\Components\Package\Importer;
use DreamFactory\Core\Components\Package\Package;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\RoleServiceAccess;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Utility\Session;

/**
 * Regression test for #174.
 *
 * Importer::import() runs inside one transaction. role_service_access rows that
 * point at a service created earlier in the same import were dropped, because
 * getNewServiceId() resolved the id through ServiceManager's cached id/name map,
 * and that map is only purged after the transaction commits.
 */
class PackageImportServiceLookupTest extends \DreamFactory\Core\Testing\TestCase
{
    const SERVICE = 'df_test_174_svc';
    const ROLE = 'df_test_174_role';

    /** @var string|null */
    protected $zipPath = null;

    public function setUp(): void
    {
        parent::setUp();
        $this->cleanup();
        Session::setUserInfoWithJWT(User::find(1));
    }

    public function tearDown(): void
    {
        $this->cleanup();
        if ($this->zipPath && file_exists($this->zipPath)) {
            unlink($this->zipPath);
        }
        parent::tearDown();
    }

    protected function cleanup()
    {
        Role::whereName(static::ROLE)->delete();
        Service::whereName(static::SERVICE)->delete();
    }

    public function testRoleServiceAccessSurvivesImportOfNewService()
    {
        $this->zipPath = $this->buildPackage();

        // Warm the cached id/name map so it predates the service the import creates,
        // which is the state of any running instance.
        $this->assertNull(ServiceManager::getServiceIdByName(static::SERVICE));

        $importer = new Importer(new Package($this->zipPath, false), false);
        $importer->import();

        $roleId = Role::whereName(static::ROLE)->value('id');
        $serviceId = Service::whereName(static::SERVICE)->value('id');
        $this->assertNotEmpty($roleId, 'role was not imported');
        $this->assertNotEmpty($serviceId, 'service was not imported');

        $rows = RoleServiceAccess::where('role_id', $roleId)->where('service_id', $serviceId)->get();
        $this->assertCount(1, $rows, 'role_service_access for a service created in the same import was dropped');
        $this->assertEquals('_table/*', $rows[0]->component);
    }

    protected function buildPackage()
    {
        $base = tempnam(sys_get_temp_dir(), 'df174_');
        unlink($base);
        $path = $base . '.zip';

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFromString('package.json', json_encode([
            'version'     => '0.1',
            'df_version'  => '7.7.0',
            'secured'     => false,
            'description' => 'regression #174',
            'service'     => ['system' => ['role' => [static::ROLE], 'service' => [static::SERVICE]]],
        ]));
        $zip->addFromString('system/service.json', json_encode([[
            'id'        => 9174,
            'name'      => static::SERVICE,
            'label'     => 'regression #174',
            'type'      => 'mysql',
            'is_active' => true,
            'mutable'   => true,
            'deletable' => true,
            'config'    => ['host' => '127.0.0.1', 'database' => 'df', 'username' => 'df', 'password' => 'df'],
        ]]));
        $zip->addFromString('system/role.json', json_encode([[
            'id'                             => 9174,
            'name'                           => static::ROLE,
            'is_active'                      => true,
            'role_service_access_by_role_id' => [[
                'id'             => 9174,
                'role_id'        => 9174,
                'service_id'     => 9174,
                'component'      => '_table/*',
                'verb_mask'      => 1,
                'requestor_mask' => 3,
                'filters'        => null,
                'filter_op'      => 'and',
            ]],
        ]]));
        $zip->close();

        return $path;
    }
}
