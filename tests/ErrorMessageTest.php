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

    private function queryException(string $driverMessage = self::DRIVER, string $sqlState = '23505'): QueryException
    {
        // PDO reports the SQLSTATE as a string code
        $driver = new class($driverMessage, $sqlState) extends \PDOException {
            public function __construct(string $message, string $sqlState)
            {
                parent::__construct($message);
                $this->code = $sqlState;
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

    public function testPostgresStatementLinesAreLeftOut(): void
    {
        $cases = [
            // statement line and position marker, then a hint
            [
                "SQLSTATE[42703]: Undefined column: 7 ERROR:  column \"nosuch\" does not exist\n"
                . "LINE 1: select * from \"orders\" where \"nosuch\" = \$1\n"
                . "                                       ^\n"
                . "HINT:  Perhaps you meant to reference the column \"orders.note\".",
                '42703',
                "SQLSTATE[42703]: Undefined column: 7 ERROR:  column \"nosuch\" does not exist\n"
                . "HINT:  Perhaps you meant to reference the column \"orders.note\".",
            ],
            // position marker as the last line
            [
                "SQLSTATE[42601]: Syntax error: 7 ERROR:  syntax error at or near \"(\"\n"
                . "LINE 1: ...regate\" from \"public\".\"orders\" where (\"born\" < \$1())\n"
                . "                                                         ^",
                '42601',
                "SQLSTATE[42601]: Syntax error: 7 ERROR:  syntax error at or near \"(\"",
            ],
            // parameter context
            [
                "SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type integer: \"abc\"\n"
                . "CONTEXT:  unnamed portal parameter \$1 = '...'",
                '22P02',
                "SQLSTATE[22P02]: Invalid text representation: 7 ERROR:  invalid input syntax for type integer: \"abc\"",
            ],
        ];
        foreach ($cases as [$driver, $state, $expected]) {
            $this->logged = [];
            $qe = $this->queryException($driver, $state);

            $error = ResponseFactory::exceptionToArray($qe);
            $this->assertSame(htmlentities($expected, ENT_COMPAT), $error['message']);
            $this->assertSame($state, $error['code']);
            $this->assertNoDetails($error['message']);
            $this->assertSame([['error', $qe->getMessage()]], $this->logged);

            $wrapped = new InternalServerErrorException("Failed to retrieve records from 'orders'.\n" . $qe->getMessage());
            $this->assertSame(
                htmlentities("Failed to retrieve records from 'orders'.\n" . $expected, ENT_COMPAT),
                $wrapped->toArray()['message']
            );
        }
    }

    public function testDebugKeepsTheStatementLines(): void
    {
        $this->boot(true);
        $driver = "SQLSTATE[42601]: Syntax error: 7 ERROR:  syntax error at or near \"(\"\nLINE 1: select (\n               ^";
        $qe = $this->queryException($driver, '42601');

        $this->assertSame(htmlentities($qe->getMessage(), ENT_COMPAT), ResponseFactory::exceptionToArray($qe)['message']);
        $this->assertStringContainsString('LINE 1: ', ResponseFactory::exceptionToArray($qe)['message']);
    }

    public function testWithoutStatementLines(): void
    {
        // nothing to leave out
        $this->assertSame(self::DRIVER, ErrorMessage::withoutStatementLines(self::DRIVER));
        $this->assertSame('LINE 1: abc', ErrorMessage::withoutStatementLines('LINE 1: abc'));
        // multi-line context up to the end, details and hints kept
        $this->assertSame(
            "ERROR:  boom\nDETAIL:  first\nsecond\nHINT:  try",
            ErrorMessage::withoutStatementLines("ERROR:  boom\nDETAIL:  first\nsecond\nHINT:  try\nQUERY:  select 1\nCONTEXT:  PL/pgSQL function f() line 3 at SQL statement\nSQL statement \"select 1\"")
        );
        // a line below a statement line that is not a position marker stays
        $this->assertSame("ERROR:  x\nDETAIL:  y", ErrorMessage::withoutStatementLines("ERROR:  x\nLINE 2: z\nDETAIL:  y"));
    }

    public function testFullMessageIsLoggedOncePerException(): void
    {
        $qe = $this->queryException();
        ResponseFactory::exceptionToArray($qe);
        ResponseFactory::exceptionToArray($qe);
        $this->assertCount(1, $this->logged);

        $wrapped = new InternalServerErrorException("Failed to retrieve records from 'orders'.\n" . $qe->getMessage());
        $wrapped->toArray();
        $wrapped->toArray();
        $this->assertCount(2, $this->logged);

        ResponseFactory::exceptionToArray($this->queryException());
        $this->assertCount(3, $this->logged);
    }

    public function testBatchResponseEntriesHaveTheDriverMessageOnly(): void
    {
        $mysql = "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'A1' for key 'code'";
        $first = $this->queryException();
        $second = $this->queryException($mysql, '23000');
        $cases = [
            // rollback: the first failing record ends the batch
            'Batch Error: Not all requested records could be created. All changes rolled back.' => [0 => ['id' => 1], 1 => $first],
            // continue: every record is attempted
            'Batch Error: Not all requested records could be created.' => [
                0 => $first,
                1 => ['id' => 2],
                2 => $second,
                3 => new BadRequestException("Failed to create records in 'orders'.\n" . $second->getMessage()),
            ],
        ];
        foreach ($cases as $message => $entries) {
            $response = ResponseFactory::exceptionToServiceResponse(new BatchException($entries, $message));
            $this->assertSame(400, $response->getStatusCode());
            $error = $response->getContent()['error'];
            $this->assertSame($message, $error['message']);
            $this->assertNoDetails(json_encode($error));
            foreach ($entries as $index => $entry) {
                $resource = $error['context']['resource'][$index];
                if (is_array($entry)) {
                    $this->assertSame($entry, $resource);
                } elseif ($entry === $first) {
                    $this->assertSame(htmlentities(self::DRIVER, ENT_COMPAT), $resource['message']);
                } elseif ($entry === $second) {
                    $this->assertSame(htmlentities($mysql, ENT_COMPAT), $resource['message']);
                    $this->assertSame('23000', $resource['code']);
                } else {
                    $this->assertSame(htmlentities("Failed to create records in 'orders'.\n" . $mysql, ENT_COMPAT), $resource['message']);
                    $this->assertSame(400, $resource['code']);
                }
            }
        }
    }

    private const ORACLE_STATEMENT = "insert into \"ORDERS\" (\"ID\", \"CARD\")\nvalues (:p0, :p1)";

    private function oracleDriverMessage(string $bindings = "1,4111111111111111"): string
    {
        // the error text yajra/laravel-pdo-via-oci8 builds for a failed statement
        return 'Error Code    : 1' . PHP_EOL
            . 'Error Message : ORA-00001: unique constraint (SHOP.ORDERS_PK) violated' . PHP_EOL
            . 'Position      : 0' . PHP_EOL
            . 'Statement     : ' . self::ORACLE_STATEMENT . PHP_EOL
            . 'Bindings      : [' . $bindings . ']' . PHP_EOL;
    }

    private function assertNoOracleStatementLines(string $text): void
    {
        foreach (['Position', 'Statement', 'Bindings', 'ORDERS"', ':p0', '4111111111111111', 'multi'] as $part) {
            $this->assertStringNotContainsString($part, $text);
        }
    }

    public function testOracleStatementLinesAreLeftOut(): void
    {
        $expected = "Error Code    : 1\nError Message : ORA-00001: unique constraint (SHOP.ORDERS_PK) violated";
        $driver = $this->oracleDriverMessage("1,\"multi\nline\"");

        // through Laravel's QueryException
        $qe = $this->queryException($driver, '1');
        $error = ResponseFactory::exceptionToArray($qe);
        $this->assertSame(htmlentities($expected, ENT_COMPAT), $error['message']);
        $this->assertNoDetails($error['message']);
        $this->assertNoOracleStatementLines($error['message']);
        $this->assertSame([['error', $qe->getMessage()]], $this->logged);

        // wrapped in a service message
        $wrapped = new InternalServerErrorException("Failed to create records in 'orders'.\n" . $qe->getMessage());
        $message = $wrapped->toArray()['message'];
        $this->assertSame(htmlentities("Failed to create records in 'orders'.\n" . $expected, ENT_COMPAT), $message);
        $this->assertNoOracleStatementLines($message);

        // the driver's exception on its own
        $this->logged = [];
        $direct = new \PDOException($this->oracleDriverMessage());
        $this->assertSame(htmlentities($expected, ENT_COMPAT), ResponseFactory::exceptionToArray($direct)['message']);
        $this->assertSame([['error', $direct->getMessage()]], $this->logged);

        // in a batch entry
        $batch = (new BatchException([0 => $this->queryException($this->oracleDriverMessage(), '1')], 'Batch Error'))->toArray();
        $this->assertSame(htmlentities($expected, ENT_COMPAT), $batch['context']['resource'][0]['message']);
        $this->assertNoOracleStatementLines(json_encode($batch));
    }

    public function testDebugKeepsTheOracleStatementLines(): void
    {
        $this->boot(true);
        $direct = new \PDOException($this->oracleDriverMessage());
        $this->assertSame(htmlentities($direct->getMessage(), ENT_COMPAT), ResponseFactory::exceptionToArray($direct)['message']);
        $this->assertSame([], $this->logged);
    }

    public function testWithoutOracleDriverLines(): void
    {
        $this->assertSame(
            "Error Code    : 942\nError Message : ORA-00942: table or view does not exist",
            ErrorMessage::withoutOracleDriverLines("Error Code    : 942\nError Message : ORA-00942: table or view does not exist\nPosition      : 14\nStatement     : select * from \"NOPE\"\nBindings      : []\n")
        );
        // a message stack in the error text stays
        $this->assertSame(
            "Error Code    : 20001\nError Message : ORA-20001: x\nORA-06512: at line 1",
            ErrorMessage::withoutOracleDriverLines("Error Code    : 20001\nError Message : ORA-20001: x\nORA-06512: at line 1\nPosition      : 0\nStatement     : begin p; end;\nBindings      : []")
        );
        // without a position line, from the first later line on
        $this->assertSame('Error Code    : 1', ErrorMessage::withoutOracleDriverLines("Error Code    : 1\nBindings      : [x]\nStatement     : y"));
        // nothing to leave out
        $this->assertSame('Error Code    : 1', ErrorMessage::withoutOracleDriverLines('Error Code    : 1'));
        $this->assertSame("Statement     : x\nBindings      : y", ErrorMessage::withoutOracleDriverLines("Statement     : x\nBindings      : y"));
        $this->assertSame('Position      : 3', ErrorMessage::forResponse(new \RuntimeException('Position      : 3')));
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
