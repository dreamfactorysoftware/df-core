<?php

use DreamFactory\Core\Contracts\DataModelEnricherInterface;
use DreamFactory\Core\Database\Models\DbFieldExtras;
use DreamFactory\Core\Database\Models\DbTableExtras;
use DreamFactory\Core\Models\User;
use DreamFactory\Core\Services\BaseRestService;
use DreamFactory\Core\Utility\Session;

/**
 * GET {service}/_spec?model=true&enrich=true (MCP get_data_model) carries the
 * meaning an admin recorded in the schema extras, and packages can add to the
 * model through DataModelEnricherInterface without being able to break it.
 * Without enrich=true the model is the 1.0.19 one.
 *
 * Runs against a real install: needs a database service `db` with tables
 * `customers` and `orders`, and user 1. Skipped when they are absent. Every
 * schema extra it touches is snapshotted first and put back afterwards.
 *
 * @group integration
 */
class DataModelSemanticsTest extends \DreamFactory\Core\Testing\TestCase
{
    /** Never stage (migrate + db:seed) here: these tests run against a real install. */
    protected static $staged = true;

    private const PLAIN_DESCRIPTION = 'Condensed data model for LLM consumption. Shows all tables, their columns with types, foreign key references, structural patterns, sample data, and enum values.';

    private int $dbServiceId = 0;

    /** @var array<string, array<string, mixed>|null>|null rows as they were before the test; null = nothing snapshotted */
    private ?array $snapshot = null;

    public function setUp(): void
    {
        parent::setUp();
        $user = User::find(1);
        $this->dbServiceId = (int) \DreamFactory\Core\Models\Service::whereName('db')->value('id');
        if (!$user || $this->dbServiceId === 0) {
            $this->markTestSkipped('needs user 1 and a database service named db');
        }
        Session::setUserInfoWithJWT($user);
        try {
            $svc = ServiceManager::getService('db');
            $hasTables = $svc->getTableSchema('customers') && $svc->getTableSchema('orders');
        } catch (\Throwable $e) {
            $hasTables = false;
        }
        if (!$hasTables) {
            $this->markTestSkipped('service db needs tables customers and orders');
        }
        $this->snapshot = [
            'table:customers' => $this->row(DbTableExtras::class, ['table' => 'customers']),
            'field:customers.email' => $this->row(DbFieldExtras::class, ['table' => 'customers', 'field' => 'email']),
            'field:orders.status' => $this->row(DbFieldExtras::class, ['table' => 'orders', 'field' => 'status']),
        ];
    }

    public function tearDown(): void
    {
        if ($this->snapshot !== null) {
            $this->restore(DbTableExtras::class, ['table' => 'customers'], $this->snapshot['table:customers']);
            $this->restore(DbFieldExtras::class, ['table' => 'customers', 'field' => 'email'], $this->snapshot['field:customers.email']);
            $this->restore(DbFieldExtras::class, ['table' => 'orders', 'field' => 'status'], $this->snapshot['field:orders.status']);
            ServiceManager::getService('db')->flush();
        }
        parent::tearDown();
    }

    /** @return array<string, mixed>|null the raw row, or null when there is none */
    private function row(string $class, array $key): ?array
    {
        $row = $class::where('service_id', $this->dbServiceId)->where($key)->first();

        return $row ? $row->getAttributes() : null;
    }

    /** Put a row back exactly as it was (or remove it when there was none). */
    private function restore(string $class, array $key, ?array $was): void
    {
        $class::where('service_id', $this->dbServiceId)->where($key)->delete();
        if ($was !== null) {
            \DB::table((new $class())->getTable())->insert($was);
        }
    }

    /** The model as the in-process / ?enrich=true path builds it. */
    private function model(bool $enrich = true): array
    {
        $svc = ServiceManager::getService('db');
        $m = new ReflectionMethod(BaseRestService::class, 'buildDataModel');
        $m->setAccessible(true);

        return $m->invoke($svc, true, false, null, $enrich);
    }

    /** What GET db/_spec?model=true[&enrich=true] serves. */
    private function served(array $query): array
    {
        $request = new \DreamFactory\Core\Utility\ServiceRequest();
        $request->setParameters($query);
        $svc = ServiceManager::getService('db');
        $m = new ReflectionMethod(BaseRestService::class, 'handleModelResponse');
        $m->setAccessible(true);
        $response = $m->invoke($svc, $request, true, false, (bool) ($query['enrich'] ?? false));
        ob_start();
        $response->sendContent();

        return json_decode(ob_get_clean(), true);
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

    private function recordMeaningAndAlias(): void
    {
        DbFieldExtras::updateOrCreate(
            ['service_id' => $this->dbServiceId, 'table' => 'customers', 'field' => 'email'],
            ['label' => 'Contact email', 'description' => 'Primary contact address; unique per customer', 'picklist' => null]
        );
        DbTableExtras::updateOrCreate(
            ['service_id' => $this->dbServiceId, 'table' => 'customers'],
            ['description' => 'People and companies that have placed at least one order']
        );
        DbFieldExtras::updateOrCreate(
            ['service_id' => $this->dbServiceId, 'table' => 'orders', 'field' => 'status'],
            ['alias' => 'order_status']
        );
        ServiceManager::getService('db')->flush();
    }

    private function tagEnricher(string $id, callable $enrich, ?int $priority = null): void
    {
        $this->app->bind($id, fn () => $priority === null
            ? new class($enrich) implements DataModelEnricherInterface {
                public function __construct(private $fn) {}
                public function enrich(array $model, BaseRestService $service): array { return ($this->fn)($model, $service); }
            }
            : new class($enrich, $priority) implements DataModelEnricherInterface {
                public function __construct(private $fn, private int $p) {}
                public function priority(): int { return $this->p; }
                public function enrich(array $model, BaseRestService $service): array { return ($this->fn)($model, $service); }
            });
        $this->app->tag([$id], DataModelEnricherInterface::TAG);
    }

    public function testRecordedMeaningReachesTheModel()
    {
        $this->recordMeaningAndAlias();

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
        $this->recordMeaningAndAlias();

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
    }

    /** Opt-in: without enrich=true, nothing the semantic work added reaches the model. */
    public function testModelWithoutEnrichIsThe1019Model()
    {
        $this->recordMeaningAndAlias();
        $ran = false;
        $this->tagEnricher('test.enricher.optin', function (array $model) use (&$ran) {
            $ran = true;
            $model['semantics'] = ['x' => 1];
            return $model;
        });

        foreach (['in-process' => $this->model(false), 'served' => $this->served(['model' => 'true'])] as $how => $model) {
            $this->assertSame(self::PLAIN_DESCRIPTION, $model['description'], "$how: 1.0.19 description");
            $this->assertArrayNotHasKey('semantics', $model, "$how: no enricher block");
            $this->assertSame(['service', 'description', 'tables'], array_slice(array_keys($model), 0, 3), "$how: 1.0.19 key order");
            $this->assertArrayNotHasKey('description', $model['tables']['customers'], "$how: no table description");
            $email = self::column($model, 'customers', 'email');
            foreach (['label', 'description', 'allowed_values', 'column'] as $k) {
                $this->assertArrayNotHasKey($k, $email, "$how: no $k on a column");
            }
            $status = self::column($model, 'orders', 'status');
            $this->assertArrayNotHasKey('column', $status, "$how: aliased field keeps its database name");
            foreach ($model['tables']['orders']['sample_data'] ?? [] as $row) {
                $this->assertArrayHasKey('status', $row, "$how: samples keyed by database column");
            }
        }
        $this->assertFalse($ran, 'no enricher runs without enrich=true');

        $served = $this->served(['model' => 'true', 'enrich' => 'true']);
        $this->assertTrue($ran, 'enrich=true runs the enrichers');
        $this->assertSame(['service', 'description', 'semantics'], array_slice(array_keys($served), 0, 3));
        $this->assertSame('order_status', self::column($served, 'orders', 'order_status')['name']);
    }

    public function testEnrichersRunByPriorityStableForTies()
    {
        $mark = fn (string $name) => function (array $model) use ($name) {
            $model['order'][] = $name;
            return $model;
        };
        $this->tagEnricher('test.enricher.late', $mark('late'), 5);
        $this->tagEnricher('test.enricher.none1', $mark('none1'));
        $this->tagEnricher('test.enricher.early', $mark('early'), -100);
        $this->tagEnricher('test.enricher.zero', $mark('zero'), 0);
        $this->tagEnricher('test.enricher.none2', $mark('none2'));

        $order = array_values(array_filter($this->model()['order'] ?? [], fn ($n) => in_array($n, ['late', 'none1', 'early', 'zero', 'none2'], true)));
        $this->assertSame(['early', 'none1', 'zero', 'none2', 'late'], $order);
    }

    public function testSemanticsOnlyModeReturnsJustEnricherAdditions()
    {
        $this->tagEnricher('test.enricher.sem', function (array $model) {
            $model['semantics'] = ['tables_seen' => array_keys($model['tables'])];
            return $model;
        });

        $svc = ServiceManager::getService('db');
        $m = new ReflectionMethod(BaseRestService::class, 'handleSemanticsResponse');
        $m->setAccessible(true);
        $response = $m->invoke($svc);
        ob_start();
        $response->sendContent();
        $out = json_decode(ob_get_clean(), true);
        $this->assertSame(['service', 'semantics'], array_keys($out));
        $this->assertContains('customers', $out['semantics']['tables_seen']);
        $this->assertSame($out, $svc->getDataModelSemantics(), 'the in-process accessor serves the same');

        // In the enriched model the enricher's block comes right after the description.
        $full = $this->model();
        $this->assertSame(['service', 'description', 'semantics'], array_slice(array_keys($full), 0, 3));
    }

    public function testStockModeIsUnchanged()
    {
        $this->recordMeaningAndAlias();
        $svc = ServiceManager::getService('db');
        $m = new ReflectionMethod(BaseRestService::class, 'buildDataModel');
        $m->setAccessible(true);
        $stock = $m->invoke($svc, true, true, null, true);
        $this->assertArrayNotHasKey('description', self::column($stock, 'customers', 'email'));
    }

    public function testEnrichersAddAndAFailingOneIsSkipped()
    {
        $this->tagEnricher('test.enricher.bad', function () {
            throw new RuntimeException('boom');
        });
        $this->tagEnricher('test.enricher.ok', function (array $model, BaseRestService $service) {
            $model['glossary'] = [['term' => 'active customer', 'service' => $service->getName()]];
            return $model;
        });

        $model = $this->model();
        $this->assertSame('active customer', $model['glossary'][0]['term'] ?? null);
        $this->assertSame('db', $model['glossary'][0]['service'] ?? null);
        $this->assertArrayHasKey('customers', $model['tables'], 'model still served');
    }
}
