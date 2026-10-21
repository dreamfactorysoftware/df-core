<?php

require_once __DIR__ . '/AzionImportTestCase.php';

use DreamFactory\Core\Models\EmailTemplate;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;

/**
 * Service configs reference other records by the source instance's ids: the user
 * service's invite/password email service and template, open_reg_role_id, Excel's
 * storage_service_id, and so on. On a 4.x -> 7.7 import the email service id (6) points
 * at logs. remapServiceConfigIds maps them by name; on overwrite an unmapped id is
 * dropped (target keeps its value), on insert it is kept (as before).
 */
class PackageImportServiceConfigIdsTest extends AzionImportTestCase
{
    /** @var array */
    protected $userConfigBefore;

    public function setUp(): void
    {
        parent::setUp();
        $this->userConfigBefore = Service::whereName('user')->first()->config;
    }

    public function tearDown(): void
    {
        // Restore the user service config the overwrite tests change.
        if (is_array($this->userConfigBefore)) {
            \Illuminate\Database\Eloquent\Model::unguarded(function () {
                Service::whereName('user')->first()->update(['config' => $this->userConfigBefore]);
            });
        }
        parent::tearDown();
    }

    protected function remap(array $files, array $config, $overwrite, $dfVersion = '4.13.0')
    {
        $files += ['system/role.json' => [$this->role(9600, static::PREFIX . 'manifest_filler')]];
        $importer = $this->importerFor($this->buildPackage($files, $dfVersion));
        $service = ['name' => static::PREFIX . 'unit', 'config' => $config];
        $args = [&$service, $overwrite];
        $method = new \ReflectionMethod($importer, 'remapServiceConfigIds');
        $method->setAccessible(true);
        $method->invokeArgs($importer, $args);

        return $service['config'];
    }

    protected function remapOverwrite(array $files, $api, array $record, $dfVersion = '4.13.0')
    {
        $files += ['system/role.json' => [$this->role(9600, static::PREFIX . 'manifest_filler')]];
        $importer = $this->importerFor($this->buildPackage($files, $dfVersion), true);
        $args = [$api, &$record];
        $method = new \ReflectionMethod($importer, 'remapOverwriteRecord');
        $method->setAccessible(true);
        $method->invokeArgs($importer, $args);

        return $record;
    }

    protected function userServiceRecord(array $config)
    {
        return [
            'id'          => 7,
            'name'        => 'user',
            'label'       => 'User Management',
            'description' => 'Service for managing system users.',
            'type'        => 'user',
            'is_active'   => true,
            'mutable'     => true,
            'deletable'   => false,
            'config'      => $config,
        ];
    }

    // ---------------------------------------------------------------- remapServiceConfigIds

    public function testServiceInPackageAndOnTargetIsMappedByName()
    {
        $svc = $this->unguarded(Service::class, ['name' => static::PREFIX . 'cfg_mail', 'label' => 'm', 'type' => 'local_file',
            'is_active' => true, 'config' => ['container' => 'app']]);
        $config = $this->remap(
            ['system/service.json' => [$this->fileService($svc->name, 9601)]],
            ['invite_email_service_id' => 9601],
            false
        );
        $this->assertSame($svc->id, $config['invite_email_service_id']);
    }

    public function testStringAndIntIdsMatch()
    {
        $svc = $this->unguarded(Service::class, ['name' => static::PREFIX . 'cfg_mail2', 'label' => 'm', 'type' => 'local_file',
            'is_active' => true, 'config' => ['container' => 'app']]);
        $files = ['system/service.json' => [$this->fileService($svc->name, '9602')]];
        $this->assertSame($svc->id, $this->remap($files, ['invite_email_service_id' => 9602], true)['invite_email_service_id']);
        $files = ['system/service.json' => [$this->fileService($svc->name, 9602)]];
        $this->assertSame($svc->id, $this->remap($files, ['invite_email_service_id' => '9602'], true)['invite_email_service_id']);
    }

    public function testUnresolvedIdIsDroppedOnOverwriteAndKeptOnInsert()
    {
        // 4.x email = 6; on 7.7 id 6 is logs. Not in the package, so it cannot be mapped.
        $config = ['invite_email_service_id' => 6, 'password_email_service_id' => 6, 'allow_open_registration' => false];
        $this->assertSame(['allow_open_registration' => false], $this->remap([], $config, true));
        $this->assertSame($config, $this->remap([], $config, false));
    }

    public function testOwnServiceIdDefaultRoleNonNumericAndZeroAreUntouched()
    {
        $config = [
            'service_id'               => 9603,
            'default_role'             => 9604,
            'invite_email_service_id'  => 'not-a-number',
            'password_email_service_id' => 0,
            'open_reg_email_service_id' => '',
            'open_reg_role_id'         => null,
            'label_template_id'        => '12abc',
        ];
        $this->assertSame($config, $this->remap([], $config, true));
        $this->assertSame($config, $this->remap([], $config, false));
    }

    public function testRoleAndTemplateIdsAreMappedByName()
    {
        $role = $this->createRole(static::PREFIX . 'cfg_role');
        $inviteId = EmailTemplate::whereName('User Invite Default')->value('id');
        $config = $this->remap([
            'system/role.json'           => [$this->role(9605, $role->name)],
            'system/email_template.json' => [['id' => 9606, 'name' => 'User Invite Default']],
        ], ['open_reg_role_id' => 9605, 'invite_email_template_id' => '9606'], true);

        $this->assertSame($role->id, $config['open_reg_role_id']);
        $this->assertEquals($inviteId, $config['invite_email_template_id']);
    }

    public function testMissingEmailTemplateJsonDoesNotFail()
    {
        $config = ['invite_email_template_id' => 1, 'password_email_template_id' => 3];
        $this->assertSame([], $this->remap([], $config, true));
        $this->assertSame($config, $this->remap([], $config, false));
    }

    /**
     * app_role_map (OAuth/SAML/LDAP services) nests app_id/role_id one level down. Only
     * top-level config fields are mapped, so these keep the source ids. Pre-existing.
     *
     * @group known-gap
     */
    public function testNestedAppRoleMapIsMapped()
    {
        $role = $this->createRole(static::PREFIX . 'cfg_arm_role');
        $config = $this->remap(
            ['system/role.json' => [$this->role(9607, $role->name)]],
            ['default_role' => 9607, 'app_role_map' => [['app_id' => 1, 'role_id' => 9607]]],
            true
        );
        $this->assertSame($role->id, $config['app_role_map'][0]['role_id'], 'nested app_role_map role_id kept the source id');
    }

    // ---------------------------------------------------------------- remapOverwriteRecord

    public function testOverwriteMapsDefaultRoleAndDropsUnmapped()
    {
        $role = $this->createRole(static::PREFIX . 'cfg_def_role');
        $files = ['system/role.json' => [$this->role(9608, $role->name)]];

        $rec = $this->remapOverwrite($files, 'system/service', ['name' => 'x', 'config' => ['default_role' => 9608, 'x' => 1]]);
        $this->assertSame(['default_role' => $role->id, 'x' => 1], $rec['config']);

        $rec = $this->remapOverwrite($files, 'system/service', ['name' => 'x', 'config' => ['default_role' => 9999, 'x' => 1]]);
        $this->assertSame(['x' => 1], $rec['config']);
    }

    public function testOverwriteDropsSourceAuditIds()
    {
        $rec = $this->remapOverwrite([], 'system/role', ['name' => 'x', 'created_by_id' => 5, 'last_modified_by_id' => 5]);
        $this->assertSame(['name' => 'x'], $rec);
    }

    // ---------------------------------------------------------------- end to end

    public function testOverwriteUserServiceKeepsTargetEmailServiceAndTemplate()
    {
        $emailId = Service::whereName('email')->value('id');
        $before = Service::whereName('user')->first()->config;
        $this->assertEquals($emailId, $before['invite_email_service_id'], 'fixture: seeded user service config');

        // 4.x ids: email = 6 (logs on 7.7), template 1 = invite.
        $zip = $this->buildPackage(['system/service.json' => [$this->userServiceRecord([
            'allow_open_registration'    => false,
            'invite_email_service_id'    => 6,
            'invite_email_template_id'   => 1,
            'password_email_service_id'  => 6,
            'password_email_template_id' => 3,
        ])]], '4.13.0');

        $this->import($zip, true);

        $after = Service::whereName('user')->first()->config;
        $this->assertEquals($emailId, $after['invite_email_service_id'], 'invite email service now points at the source id (logs)');
        $this->assertEquals($emailId, $after['password_email_service_id']);
        $this->assertEquals($before['invite_email_template_id'], $after['invite_email_template_id']);
        $this->assertEquals($before['password_email_template_id'], $after['password_email_template_id']);
    }

    public function testOverwriteUserServiceMapsEmailServiceInPackage()
    {
        $emailId = Service::whereName('email')->value('id');
        $zip = $this->buildPackage(['system/service.json' => [
            [
                'id' => 6, 'name' => 'email', 'label' => 'Local Email Service', 'type' => 'local_email',
                'is_active' => true, 'mutable' => true, 'deletable' => true, 'config' => [],
            ],
            $this->userServiceRecord(['invite_email_service_id' => 6, 'password_email_service_id' => '6']),
        ]], '4.13.0');

        $this->import($zip, true);

        $after = Service::whereName('user')->first()->config;
        $this->assertEquals($emailId, $after['invite_email_service_id']);
        $this->assertEquals($emailId, $after['password_email_service_id']);
    }

    // ---------------------------------------------------------------- required *_service_id (Excel)

    protected function excelRecord($name, $storageServiceId)
    {
        return [
            'id'        => 9711,
            'name'      => $name,
            'label'     => $name,
            'type'      => 'excel',
            'is_active' => true,
            'mutable'   => true,
            'deletable' => true,
            'config'    => ['storage_service_id' => $storageServiceId, 'storage_container' => '/'],
        ];
    }

    protected function requireExcel()
    {
        if (empty(\ServiceManager::getServiceType('excel'))) {
            $this->markTestSkipped('df-excel not installed');
        }
    }

    protected function excelStorageId($name)
    {
        $id = Service::whereName($name)->value('id');

        return \DB::table('excel_config')->where('service_id', $id)->value('storage_service_id');
    }

    public function testExcelStorageServiceOnTargetIsMappedOnInsert()
    {
        $this->requireExcel();
        $store = $this->unguarded(Service::class, ['name' => static::PREFIX . 'xls_store', 'label' => 's', 'type' => 'local_file',
            'is_active' => true, 'config' => ['container' => 'app']]);
        $xls = static::PREFIX . 'xls1';
        $zip = $this->buildPackage(['system/service.json' => [
            $this->fileService($store->name, 9710),
            $this->excelRecord($xls, 9710),
        ]], '4.13.0');

        $this->import($zip);

        $this->assertEquals($store->id, $this->excelStorageId($xls));
    }

    public function testExcelStorageServiceCreatedInSameImportKeepsPackageValue()
    {
        // Both services are new. The storage service does not exist yet when the excel
        // config is remapped, so the package value is kept (as before the fix). Dropping it
        // would fail Excel's required storage_service_id rule. Source id 5 exists on the
        // target (files), so validation passes; it points at files, not at xls_store2.
        $this->requireExcel();
        $xls = static::PREFIX . 'xls2';
        $zip = $this->buildPackage(['system/service.json' => [
            $this->fileService(static::PREFIX . 'xls_store2', 5),
            $this->excelRecord($xls, 5),
        ]], '4.13.0');

        $this->import($zip);

        $this->assertNotEmpty(Service::whereName($xls)->value('id'), 'excel service not imported');
        $this->assertNotEmpty(Service::whereName(static::PREFIX . 'xls_store2')->value('id'));
        $this->assertEquals(5, $this->excelStorageId($xls));
    }
}
