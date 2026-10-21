<?php

require_once __DIR__ . '/AzionImportTestCase.php';

use DreamFactory\Core\Components\ServiceHealthChecker;
use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Models\Role;
use DreamFactory\Core\Models\Service;
use DreamFactory\Core\Services\ServiceType;
use Illuminate\Log\Events\MessageLogged;

/**
 * Service::created runs a health check (GET _table for database services). An oci8
 * service imported with a masked (null) password threw a TypeError from the driver; the
 * checker caught only \Exception, so the TypeError escaped and aborted the whole import.
 */
class PackageImportHealthCheckTest extends AzionImportTestCase
{
    /** @var array */
    protected $logged = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->logged = [];
        \Event::listen(MessageLogged::class, function (MessageLogged $e) {
            $this->logged[] = $e;
        });
        foreach (['az_t_type_error' => \TypeError::class, 'az_t_error' => \Error::class] as $type => $class) {
            \ServiceManager::addType(new ServiceType([
                'name'        => $type,
                'label'       => $type,
                'description' => 'throws ' . $class . ' on its first request',
                'group'       => ServiceTypeGroups::DATABASE,
                'factory'     => function ($config) use ($class) {
                    // Builds fine; throws when the first request opens the connection,
                    // like oci8 given the masked (null) password from a package.
                    return new AzThrowingDbService($config, $class);
                },
            ]));
        }
    }

    protected function healthFailures()
    {
        return array_values(array_filter($this->logged, function (MessageLogged $e) {
            return $e->level === 'error' && $e->message === 'Post-creation API health check FAILED.';
        }));
    }

    protected function serviceRecord($id, $name, $type)
    {
        return [
            'id'        => $id,
            'name'      => $name,
            'label'     => $name,
            'type'      => $type,
            'is_active' => true,
            'mutable'   => true,
            'deletable' => true,
            'config'    => [],
        ];
    }

    public function testCheckerReturnsFalseOnTypeError()
    {
        $service = Service::withoutEvents(function () {
            return \Illuminate\Database\Eloquent\Model::unguarded(function () {
                return Service::create(['name' => static::PREFIX . 'hc_direct', 'label' => 'hc', 'type' => 'az_t_type_error',
                    'is_active' => true]);
            });
        });

        $this->assertFalse((new ServiceHealthChecker())->check($service));

        $failures = $this->healthFailures();
        $this->assertCount(1, $failures);
        $this->assertEquals(\TypeError::class, $failures[0]->context['root_cause_class']);
    }

    public function testImportOfOracleServiceWithMaskedPasswordCompletes()
    {
        if (empty(\ServiceManager::getServiceType('oracle')) || !extension_loaded('oci8')) {
            $this->markTestSkipped('df-oracledb / oci8 not installed');
        }
        // A package exported without secrets has password null. yajra's Oci8::connectOracle()
        // takes string $password, so the health check's first request throws a TypeError.
        $name = static::PREFIX . 'hc_real_oracle';
        $record = $this->serviceRecord(9805, $name, 'oracle');
        $record['config'] = ['host' => '127.0.0.1', 'port' => 1521, 'database' => 'XE', 'username' => 'u', 'password' => null];
        $zip = $this->buildPackage(['system/service.json' => [$record]], '4.13.0');

        $this->import($zip);

        $this->assertNotEmpty(Service::whereName($name)->value('id'), 'oracle service not imported');
        $failures = $this->healthFailures();
        $this->assertCount(1, $failures);
        $this->assertEquals(\TypeError::class, $failures[0]->context['root_cause_class']);
    }

    public function testImportCompletesWhenHealthCheckThrowsTypeOrError()
    {
        $role = static::PREFIX . 'hc_role';
        $zip = $this->buildPackage([
            'system/service.json' => [
                $this->serviceRecord(9801, static::PREFIX . 'hc_oracle', 'az_t_type_error'),
                $this->serviceRecord(9802, static::PREFIX . 'hc_error', 'az_t_error'),
                $this->fileService(static::PREFIX . 'hc_files', 9803),
            ],
            'system/role.json'    => [$this->role(9804, $role, [$this->rsa(1, 9804, 9801, '_table/*')])],
        ], '4.13.0');

        $this->import($zip);

        $this->assertEquals(0, \DB::transactionLevel(), 'import left its transaction open');
        foreach (['hc_oracle', 'hc_error', 'hc_files'] as $name) {
            $this->assertNotEmpty(Service::whereName(static::PREFIX . $name)->value('id'), $name . ' not imported');
        }
        $this->assertSame([static::PREFIX . 'hc_oracle:_table/*'], $this->grantServices($role),
            'later import steps did not run');
        $classes = array_map(function (MessageLogged $e) {
            return $e->context['root_cause_class'];
        }, $this->healthFailures());
        sort($classes);
        $this->assertSame([\Error::class, \TypeError::class], $classes);
    }
}

class AzThrowingDbService extends \DreamFactory\Core\Services\BaseRestService
{
    /** @var string */
    protected $throwClass;

    public function __construct($settings, $throwClass)
    {
        parent::__construct($settings);
        $this->throwClass = $throwClass;
    }

    public function handleRequest(\DreamFactory\Core\Contracts\ServiceRequestInterface $request, $resource = null)
    {
        $class = $this->throwClass;
        throw new $class('oci_connect(): Argument #2 ($password) must be of type string, null given');
    }
}
