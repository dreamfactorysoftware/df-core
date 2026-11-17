<?php

use DreamFactory\Core\Components\ExceptionResponse;
use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\BatchException;
use DreamFactory\Core\Exceptions\InternalServerErrorException;
use DreamFactory\Core\Utility\ErrorMessage;
use DreamFactory\Core\Utility\ResponseFactory;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Error responses carry the database driver's message without the connection and
 * statement details Laravel's QueryException appends; the full message goes to the
 * log at error level. app.debug keeps the full message in the response. Status
 * codes and error codes do not change.
 *
 * Runs on a bare container (config and log only), no application boot.
 */
class ErrorMessageTest extends TestCase
{
    const DRIVER = "SQLSTATE[23505]: Unique violation: 7 ERROR:  duplicate key value violates unique constraint \"orders_pkey\"\nDETAIL:  Key (id)=(1) already exists.";

    /** @var array list of [level, message] written to the log */
    private $logged;

    private $previousContainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousContainer = Container::getInstance();
        $this->boot(false);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Container::setInstance($this->previousContainer);
        parent::tearDown();
    }

    private function boot(bool $debug): void
    {
        $this->logged = [];
        $logged = &$this->logged;
        $container = new Container();
        Container::setInstance($container);
        $container->instance('config', new Repository(['app' => ['debug' => $debug], 'df' => ['resources_wrapper' => 'resource']]));
        $container->instance('log', new class($logged) extends AbstractLogger {
            private $entries;

            public function __construct(array &$entries)
            {
                $this->entries = &$entries;
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->entries[] = [$level, (string)$message];
            }
        });
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($container);
        if (!class_exists('Config', false)) {
            class_alias(\Illuminate\Support\Facades\Config::class, 'Config');
        }
    }

    private function queryException(): QueryException
    {
        // PDO reports the SQLSTATE as a string code
        $driver = new class(self::DRIVER) extends \PDOException {
            public function __construct(string $message)
            {
                parent::__construct($message);
                $this->code = '23505';
            }
        };

        return new QueryException(
            'service.shop',
            'insert into "orders" ("id", "note") values (?, ?)',
            [1, 'gift'],
            $driver,
            ['driver' => 'pgsql', 'host' => 'db.internal.example', 'port' => 5432, 'database' => 'shop']
        );
    }

    private function assertNoDetails(string $text): void
    {
        foreach (['(Connection:', 'service.shop', 'Host:', 'db.internal.example', 'Port:', 'Database:', 'SQL:', 'insert into', 'gift'] as $part) {
            $this->assertStringNotContainsString($part, $text);
        }
    }

    public function testFixtureCarriesTheDetails(): void
    {
        $message = $this->queryException()->getMessage();
        $this->assertStringStartsWith(self::DRIVER . ' (Connection: service.shop, Host: db.internal.example, Port: 5432, Database: shop, SQL: insert into', $message);
    }

    public function testQueryExceptionResponseHasTheDriverMessageOnly(): void
    {
        $qe = $this->queryException();
        $error = ResponseFactory::exceptionToArray($qe);

        $this->assertSame(htmlentities(self::DRIVER, ENT_COMPAT), $error['message']);
        $this->assertSame('23505', $error['code']);
        $this->assertNoDetails($error['message']);
        $this->assertSame([['error', $qe->getMessage()]], $this->logged);
    }

    public function testStatusAndCodeAreUnchanged(): void
    {
        $response = ResponseFactory::exceptionToServiceResponse($this->queryException());
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('23505', $response->getContent()['error']['code']);

        $wrapped = new InternalServerErrorException("Failed to retrieve records from 'orders'.\n" . $this->queryException()->getMessage());
        $response = ResponseFactory::exceptionToServiceResponse($wrapped);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(500, $response->getContent()['error']['code']);
        $this->assertSame(500, $response->getContent()['error']['status_code']);
    }

    public function testWrappedMessageKeepsTheServiceTextAndDriverText(): void
    {
        $full = "Failed to retrieve records from 'orders'.\n" . $this->queryException()->getMessage();
        $error = (new InternalServerErrorException($full))->toArray();

        $this->assertSame(htmlentities("Failed to retrieve records from 'orders'.\n" . self::DRIVER, ENT_COMPAT), $error['message']);
        $this->assertNoDetails($error['message']);
        $this->assertSame([['error', $full]], $this->logged);
    }

    public function testBatchEntriesHaveTheDriverMessageOnly(): void
    {
        $batch = new BatchException([0 => $this->queryException(), 1 => ['id' => 2]], 'Batch Error: Not all requested records could be created.');
        $error = $batch->toArray();

        $this->assertSame(400, $batch->getStatusCode());
        $this->assertSame(1000, $error['code']);
        $this->assertSame('Batch Error: Not all requested records could be created.', $error['message']);
        $this->assertSame([0], $error['context']['error']);
        $entry = $error['context']['resource'][0];
        $this->assertSame('23505', $entry['code']);
        $this->assertSame(htmlentities(self::DRIVER, ENT_COMPAT), $entry['message']);
        $this->assertSame(['id' => 2], $error['context']['resource'][1]);
        $this->assertNoDetails(json_encode($error));
    }

    public function testDebugKeepsTheFullMessage(): void
    {
        $this->boot(true);
        $qe = $this->queryException();

        $this->assertSame(htmlentities($qe->getMessage(), ENT_COMPAT), ResponseFactory::exceptionToArray($qe)['message']);
        $full = "Failed to update records in 'orders'.\n" . $qe->getMessage();
        $this->assertSame(htmlentities($full, ENT_COMPAT), (new InternalServerErrorException($full))->toArray()['message']);
        $this->assertSame([], $this->logged);
    }

    public function testOtherMessagesAreUnchangedAndNotLogged(): void
    {
        foreach ([
            "Invalid or unparsable field in filter request: 'nosuch'",
            'Connection refused (Connection: none)',
            "Value 'a (Connection: b)' is too long.",
        ] as $message) {
            $this->assertSame(htmlentities($message, ENT_COMPAT), (new BadRequestException($message))->toArray()['message']);
            $this->assertSame($message, ErrorMessage::forResponse(new \RuntimeException($message)));
        }
        $this->assertSame([], $this->logged);
    }

    public function testWithoutQueryDetails(): void
    {
        // the shorter form older Laravel releases and socket connections produce
        $this->assertSame('SQLSTATE[HY000]: General error', ErrorMessage::withoutQueryDetails('SQLSTATE[HY000]: General error (Connection: mysql, SQL: select 1)'));
        $this->assertSame('SQLSTATE[HY000]: x', ErrorMessage::withoutQueryDetails('SQLSTATE[HY000]: x (Connection: m, Socket: /tmp/s, Database: d, SQL: select 1)'));
        // a value that imitates the details inside the statement cuts at the first occurrence
        $this->assertSame(
            'SQLSTATE[22001]: String data, right truncated',
            ErrorMessage::withoutQueryDetails("SQLSTATE[22001]: String data, right truncated (Connection: m, Host: h, Port: 1, Database: d, SQL: insert into t values (x (Connection: y, SQL: z)))")
        );
        $this->assertSame('', ErrorMessage::withoutQueryDetails(''));
    }
}
