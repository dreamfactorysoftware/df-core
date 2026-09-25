<?php

use DreamFactory\Core\Contracts\DataModelEnricherInterface;
use DreamFactory\Core\Database\Models\DbFieldExtras;
use DreamFactory\Core\Database\Models\DbTableExtras;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\Session;

/**
 * GET {service}/_spec?model=true (MCP get_data_model) carries the meaning an
 * admin recorded in the schema extras, and packages can add to the model
 * through DataModelEnricherInterface without being able to break it.
 */
class DataModelSemanticsTest extends \DreamFactory\Core\Testing\TestCase
{
    /** Never stage (migrate + db:seed) here: these tests run against a real install. */
    protected static $staged = true;

    private int $dbServiceId;

    public function setUp(): void
    {
        parent::setUp();
        Session::setUserInfoWithJWT(User::find(1));
        $this->dbServiceId = (int) \DreamFactory\Core\Models\Service::whereName('db')->value('id');
    }

    public function tearDown(): void
    {
        DbFieldExtras::where('service_id', $this->dbServiceId)->where('table', 'customers')->where('field', 'email')->delete();
        DbFieldExtras::where('service_id', $this->dbServiceId)->where('table', 'orders')->where('field', 'status')->whereNotNull('alias')->delete();
        DbTableExtras::where('service_id', $this->dbServiceId)->where('table', 'customers')->update(['description' => null]);
        ServiceManager::getService('db')->flush();
        parent::tearDown();
    }

    private function model(): array
    {
        $svc = ServiceManager::getService('db');
        $m = new ReflectionMethod(BaseRestService::class, 'buildDataModel');
        $m->setAccessible(true);

        return $m->invoke($svc, true, false);
    }

    private static function column(array $model, string $table, string $name): array
    {
        foreach ($model['tables'][$table]['columns'] as $c) {
            if ($c['name'] === $name) {
                return $c;
            }
        }
        self::fail("no column $table.$name");
    }

    public function testRecordedMeaningReachesTheModel()
    {
        DbFieldExtras::updateOrCreate(
            ['service_id' => $this->dbServiceId, 'table' => 'customers', 'field' => 'email'],
            ['label' => 'Contact email', 'description' => 'Primary contact address; unique per customer', 'picklist' => null]
        );
        DbTableExtras::updateOrCreate(
            ['service_id' => $this->dbServiceId, 'table' => 'customers'],
            ['description' => 'People and companies that have placed at least one order']
        );
        ServiceManager::getService('db')->flush();

        $model = $this->model();
        $email = self::column($model, 'customers', 'email');
        $this->assertSame('Contact email', $email['label']);
        $this->assertSame('Primary contact address; unique per customer', $email['description']);
        $this->assertSame('People and companies that have placed at least one order', $model['tables']['customers']['description']);

        // No invented labels: an unset label stays absent rather than a humanised name.
        $this->assertArrayNotHasKey('label', self::column($model, 'customers', 'id'));
    }

    public function testAliasedFieldsAreNamedAsTheApiReturnsThem()
    {
        DbFieldExtras::updateOrCreate(
            ['service_id' => $this->dbServiceId, 'table' => 'orders', 'field' => 'status'],
            ['alias' => 'order_status']
        );
        ServiceManager::getService('db')->flush();

        $model = $this->model();
        $col = self::column($model, 'orders', 'order_status');
        $this->assertSame('status', $col['column'], 'the database column is still reported');
        foreach ($model['tables']['orders']['sample_data'] ?? [] as $row) {
            $this->assertArrayHasKey('order_status', $row, 'samples use the API key');
            $this->assertArrayNotHasKey('status', $row);
        }
        if (isset($model['tables']['orders']['enum_values'])) {
            $this->assertArrayHasKey('order_status', $model['tables']['orders']['enum_values']);
        }

        DbFieldExtras::where('service_id', $this->dbServiceId)->where('table', 'orders')->where('field', 'status')->delete();
        ServiceManager::getService('db')->flush();
    }

    public function testStockModeIsUnchanged()
    {
        DbFieldExtras::updateOrCreate(
            ['service_id' => $this->dbServiceId, 'table' => 'customers', 'field' => 'email'],
            ['description' => 'x']
        );
        ServiceManager::getService('db')->flush();
        $svc = ServiceManager::getService('db');
        $m = new ReflectionMethod(BaseRestService::class, 'buildDataModel');
        $m->setAccessible(true);
        $stock = $m->invoke($svc, true, true);
        $this->assertArrayNotHasKey('description', self::column($stock, 'customers', 'email'));
    }

    public function testEnrichersAddAndAFailingOneIsSkipped()
    {
        $this->app->bind('test.enricher.ok', fn () => new class implements DataModelEnricherInterface {
            public function enrich(array $model, BaseRestService $service): array
            {
                $model['glossary'] = [['term' => 'active customer', 'service' => $service->getName()]];
                return $model;
            }
        });
        $this->app->bind('test.enricher.bad', fn () => new class implements DataModelEnricherInterface {
            public function enrich(array $model, BaseRestService $service): array
            {
                throw new RuntimeException('boom');
            }
        });
        $this->app->tag(['test.enricher.bad', 'test.enricher.ok'], DataModelEnricherInterface::TAG);

        $model = $this->model();
        $this->assertSame('active customer', $model['glossary'][0]['term'] ?? null);
        $this->assertSame('db', $model['glossary'][0]['service'] ?? null);
        $this->assertArrayHasKey('customers', $model['tables'], 'model still served');
    }
}
