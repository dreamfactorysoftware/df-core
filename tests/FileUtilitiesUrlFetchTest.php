<?php

use DreamFactory\Core\Exceptions\BadRequestException;
use DreamFactory\Core\Exceptions\NotFoundException;
use DreamFactory\Core\System\Components\OutboundHostAllowlist;
use DreamFactory\Core\Utility\Curl;
use DreamFactory\Core\Utility\FileUtilities;
use DreamFactory\Core\Utility\OutboundAddress;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Treats the local test server as an ordinary public host, so redirect handling can be exercised without
 * network access. Every other URL goes through the real outbound URL policy.
 */
class UrlFetchTestFileUtilities extends FileUtilities
{
    public static $origin = '';

    protected static function checkUrlTarget($url, array &$options, $allowPrivateHosts)
    {
        if ('' !== static::$origin && 0 === strpos($url, static::$origin . '/')) {
            return;
        }

        parent::checkUrlTarget($url, $options, $allowPrivateHosts);
    }
}

/**
 * Remote file downloads (FileUtilities::importUrlFileToTemp() and url_exist()).
 *
 * Starts two throwaway PHP built-in servers on 127.0.0.1: "origin", which the test double above accepts like a
 * public host, and "other", which only records the requests it receives.
 */
class FileUtilitiesUrlFetchTest extends TestCase
{
    protected static $workDir;
    protected static $servers = [];
    protected static $origin;
    protected static $other;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (!function_exists('curl_init')) {
            self::markTestSkipped('The curl extension is required.');
        }

        static::$workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'df-url-fetch-' . bin2hex(random_bytes(4));
        mkdir(static::$workDir);
        file_put_contents(static::$workDir . '/router.php', <<<'PHP'
<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
file_put_contents(__DIR__ . '/requests-' . $_SERVER['SERVER_PORT'] . '.log', $_SERVER['REQUEST_URI'] . "\n", FILE_APPEND);
if ('/file.json' === $path) {
    header('Content-Type: application/json');
    echo '{"ok":true}';
} elseif (preg_match('#^/hop/(\d+)$#', $path, $m)) {
    if ((int)$m[1] > 0) {
        header('Location: /hop/' . ((int)$m[1] - 1), true, 302);
    } else {
        echo 'done';
    }
} elseif ('/go' === $path) {
    header('Location: ' . $_GET['to'], true, (int)($_GET['code'] ?? 302));
    echo str_repeat('x', 4096);
} elseif ('/missing' === $path) {
    http_response_code(404);
    echo 'missing';
} elseif ('/error' === $path) {
    http_response_code(500);
    echo 'error';
} else {
    echo 'ok';
}
PHP
        );

        static::$origin = static::startServer();
        static::$other = static::startServer();
        UrlFetchTestFileUtilities::$origin = static::$origin;
    }

    public static function tearDownAfterClass(): void
    {
        foreach (static::$servers as $server) {
            proc_terminate($server);
            proc_close($server);
        }
        static::$servers = [];
        UrlFetchTestFileUtilities::$origin = '';

        if (static::$workDir && is_dir(static::$workDir)) {
            array_map('unlink', glob(static::$workDir . '/*'));
            rmdir(static::$workDir);
        }

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);

        parent::tearDown();
    }

    /**
     * Sets df.outbound_allowed_hosts for the rest of the test. Skips the test when the installed df-system has no
     * such setting.
     *
     * @param string $value
     */
    protected function useOutboundAllowedHosts($value)
    {
        if (!class_exists(OutboundHostAllowlist::class)) {
            $this->markTestSkipped('The installed df-system has no outbound host list.');
        }

        $container = new Container();
        $container->instance('config', new Repository(['df' => ['outbound_allowed_hosts' => $value]]));
        Container::setInstance($container);
    }

    protected static function startServer()
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $port = random_int(20000, 60000);
            $server = proc_open(
                [PHP_BINARY, '-S', '127.0.0.1:' . $port, static::$workDir . '/router.php'],
                [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes
            );
            for ($wait = 0; $wait < 50; $wait++) {
                $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
                if ($socket) {
                    fclose($socket);
                    static::$servers[] = $server;

                    return 'http://127.0.0.1:' . $port;
                }
                usleep(50000);
            }
            proc_terminate($server);
        }

        throw new \RuntimeException('Could not start a local HTTP server for the test.');
    }

    protected static function requestsReceivedBy($baseUrl)
    {
        $file = static::$workDir . '/requests-' . parse_url($baseUrl, PHP_URL_PORT) . '.log';

        return is_file($file) ? array_filter(explode("\n", file_get_contents($file))) : [];
    }

    protected function uniqueName()
    {
        return 'df-url-fetch-test-' . bin2hex(random_bytes(6)) . '.txt';
    }

    protected static function downloadDirectories()
    {
        return glob(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dfurl-*') ?: [];
    }

    protected function assertNoTempFile($name, ?array $directoriesBefore = null)
    {
        $temp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->assertFileDoesNotExist($temp . $name);
        $this->assertSame([], glob($temp . 'dfurl-*' . DIRECTORY_SEPARATOR . $name) ?: []);
        if (null !== $directoriesBefore) {
            $this->assertSame($directoriesBefore, static::downloadDirectories(), 'No download directory is left.');
        }
    }

    public function testDownloadsFile()
    {
        $file = UrlFetchTestFileUtilities::importUrlFileToTemp(static::$origin . '/file.json');

        try {
            $this->assertSame('file.json', basename($file));
            $this->assertSame('{"ok":true}', file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }

    public function testFollowsUpToFiveRedirects()
    {
        $name = $this->uniqueName();
        $file = UrlFetchTestFileUtilities::importUrlFileToTemp(static::$origin . '/hop/5', $name);

        try {
            $this->assertSame('done', file_get_contents($file), 'Only the final response body is kept.');
        } finally {
            @unlink($file);
        }
    }

    public function testStopsAfterFiveRedirects()
    {
        $name = $this->uniqueName();
        $directories = static::downloadDirectories();

        try {
            UrlFetchTestFileUtilities::importUrlFileToTemp(static::$origin . '/hop/6', $name);
            $this->fail('Expected the request to stop after five redirects.');
        } catch (BadRequestException $ex) {
            $this->assertStringContainsString('too many', $ex->getMessage());
        }

        $this->assertNoTempFile($name, $directories);
    }

    public function testEachDownloadGetsItsOwnDirectory()
    {
        $first = UrlFetchTestFileUtilities::importUrlFileToTemp(static::$origin . '/file.json');
        $second = UrlFetchTestFileUtilities::importUrlFileToTemp(static::$origin . '/hop/1', 'file.json');

        try {
            $this->assertNotSame($first, $second);
            $this->assertSame('file.json', basename($first));
            $this->assertSame('file.json', basename($second));
            $this->assertSame('{"ok":true}', file_get_contents($first));
            $this->assertSame('done', file_get_contents($second));
            $this->assertSame(['file.json'], array_values(array_diff(scandir(dirname($first)), ['.', '..'])));
        } finally {
            @unlink($first);
            @unlink($second);
        }
    }

    public function testRedirectTargetIsCheckedLikeTheOriginalUrl()
    {
        $requestsBefore = count(static::requestsReceivedBy(static::$other));
        $targets = [
            static::$other . '/file.json',
            'http://10.20.30.40/file.json',
            'http://192.168.0.10/file.json',
            'http://[::1]:' . parse_url(static::$other, PHP_URL_PORT) . '/',
        ];

        foreach ([301, 302, 303, 307, 308] as $code) {
            foreach ($targets as $target) {
                $name = $this->uniqueName();
                $directories = static::downloadDirectories();
                $url = static::$origin . '/go?code=' . $code . '&to=' . rawurlencode($target);

                try {
                    UrlFetchTestFileUtilities::importUrlFileToTemp($url, $name);
                    $this->fail("Expected the redirect ($code) to $target to be refused.");
                } catch (BadRequestException $ex) {
                    $this->assertSame(
                        'The requested URL redirects to a location that is not allowed.',
                        $ex->getMessage()
                    );
                }

                $this->assertNoTempFile($name, $directories);
            }
        }

        $this->assertCount(
            $requestsBefore,
            static::requestsReceivedBy(static::$other),
            'A refused target is never requested.'
        );
    }

    public function testRedirectToOtherSchemeIsRefused()
    {
        foreach (['file:///tmp/file.json', 'ftp://example.com/file.json', 'data:text/plain,hello'] as $target) {
            $name = $this->uniqueName();
            $directories = static::downloadDirectories();

            try {
                UrlFetchTestFileUtilities::importUrlFileToTemp(
                    static::$origin . '/go?to=' . rawurlencode($target),
                    $name
                );
                $this->fail("Expected the redirect to $target to be refused.");
            } catch (BadRequestException $ex) {
                $this->assertSame(
                    'The requested URL redirects to a location that is not allowed.',
                    $ex->getMessage()
                );
            }

            $this->assertNoTempFile($name, $directories);
        }
    }

    public function testOriginalUrlIsCheckedToo()
    {
        $requestsBefore = count(static::requestsReceivedBy(static::$other));
        $urls = [
            static::$other . '/file.json',
            'http://10.20.30.40/file.json',
            'http://[::1]:' . parse_url(static::$other, PHP_URL_PORT) . '/file.json',
            'http://df-url-fetch-test.invalid/file.json',
        ];

        foreach ($urls as $url) {
            $name = $this->uniqueName();
            $directories = static::downloadDirectories();

            try {
                FileUtilities::importUrlFileToTemp($url, $name);
                $this->fail("Expected $url to be refused.");
            } catch (BadRequestException $ex) {
                $this->assertSame('The requested URL is not allowed.', $ex->getMessage(), $url);
            }

            $this->assertNoTempFile($name, $directories);
        }

        $this->assertCount($requestsBefore, static::requestsReceivedBy(static::$other));
    }

    public function testListedHostsAreAccepted()
    {
        $port = parse_url(static::$other, PHP_URL_PORT);

        foreach (['127.0.0.1', '127.0.0.0/8', 'localhost'] as $entry) {
            $this->useOutboundAllowedHosts($entry);
            $url = ('localhost' === $entry ? 'http://localhost:' . $port : static::$other) . '/file.json';

            $file = FileUtilities::importUrlFileToTemp($url, $this->uniqueName());
            try {
                $this->assertSame('{"ok":true}', file_get_contents($file), $entry);
            } finally {
                @unlink($file);
            }

            // A redirect to a listed host is followed as well.
            $file = UrlFetchTestFileUtilities::importUrlFileToTemp(
                static::$origin . '/go?to=' . rawurlencode($url),
                $this->uniqueName()
            );
            try {
                $this->assertSame('{"ok":true}', file_get_contents($file), $entry);
            } finally {
                @unlink($file);
            }

            $this->assertTrue(FileUtilities::url_exist($url), $entry);
        }

        // Only the listed destinations.
        $this->useOutboundAllowedHosts('localhost');
        $this->expectException(BadRequestException::class);
        FileUtilities::importUrlFileToTemp(static::$other . '/file.json', $this->uniqueName());
    }

    public function testRedirectsInRemoteRequestsAcceptListedHosts()
    {
        $url = static::$origin . '/go?to=' . rawurlencode(static::$other . '/file.json');

        try {
            Curl::get($url);
            $this->fail('Expected the redirect to be refused.');
        } catch (BadRequestException $ex) {
            $this->assertSame('The requested URL redirects to a location that is not allowed.', $ex->getMessage());
        }

        $this->useOutboundAllowedHosts('127.0.0.1');
        $result = Curl::get($url);
        $this->assertSame(200, (int)Curl::getLastHttpCode());
        $this->assertEquals(['ok' => true], is_string($result) ? json_decode($result, true) : (array)$result);
    }

    public function testResolveReturnsTheCheckedAddress()
    {
        $this->assertSame('8.8.8.8', OutboundAddress::resolve('http://8.8.8.8/file.json'));
        $this->assertSame('2606:4700:4700::1111', OutboundAddress::resolve('https://[2606:4700:4700::1111]/'));

        foreach (['http://127.0.0.1/', 'http://localhost/', 'http://[::1]/', 'ftp://8.8.8.8/'] as $url) {
            try {
                OutboundAddress::resolve($url);
                $this->fail("Expected $url to be refused.");
            } catch (BadRequestException $ex) {
                $this->assertNotEmpty($ex->getMessage());
            }
        }
    }

    public function testMissingFileIsNotFound()
    {
        $name = $this->uniqueName();
        $directories = static::downloadDirectories();

        try {
            UrlFetchTestFileUtilities::importUrlFileToTemp(static::$origin . '/missing', $name);
            $this->fail('Expected a not found error.');
        } catch (NotFoundException $ex) {
            $this->assertSame('Invalid URL entered. File not found.', $ex->getMessage());
        }

        $this->assertNoTempFile($name, $directories);
    }

    public function testServerErrorIsReported()
    {
        $name = $this->uniqueName();
        $directories = static::downloadDirectories();

        try {
            UrlFetchTestFileUtilities::importUrlFileToTemp(static::$origin . '/error', $name);
            $this->fail('Expected an error.');
        } catch (BadRequestException|NotFoundException $ex) {
            $this->fail('A server error is not a client or not-found error.');
        } catch (\Exception $ex) {
            $this->assertStringContainsString('Could not read the file', $ex->getMessage());
        }

        $this->assertNoTempFile($name, $directories);
    }

    public function testOperatorUrlsMayUseAnyHost()
    {
        $name = $this->uniqueName();
        $url = static::$origin . '/go?to=' . rawurlencode(static::$other . '/hop/1');
        $file = FileUtilities::importUrlFileToTemp($url, $name, true);

        try {
            $this->assertSame('done', file_get_contents($file));
        } finally {
            @unlink($file);
        }

        // Even then, only http and https are followed, and no more than five redirects.
        try {
            FileUtilities::importUrlFileToTemp(
                static::$origin . '/go?to=' . rawurlencode('file:///tmp/file.json'),
                $this->uniqueName(),
                true
            );
            $this->fail('Expected the redirect to a file URL to be refused.');
        } catch (BadRequestException $ex) {
            $this->assertSame('The requested URL redirects to a location that is not allowed.', $ex->getMessage());
        }

        $this->expectException(BadRequestException::class);
        FileUtilities::importUrlFileToTemp(static::$origin . '/hop/6', $this->uniqueName(), true);
    }

    public function testRequestGoesToTheCheckedAddress()
    {
        $method = new \ReflectionMethod(FileUtilities::class, 'checkUrlTarget');
        $cases = [
            'http://8.8.8.8/file.json'                     => ['::8.8.8.8:80'],
            'https://8.8.4.4:8443/file.json'               => ['::8.8.4.4:8443'],
            'https://[2606:4700:4700::1111]/file.json'     => ['::[2606:4700:4700::1111]:443'],
        ];

        foreach ($cases as $url => $expected) {
            $options = [];
            $method->invokeArgs(null, [$url, &$options, false]);
            $this->assertSame($expected, $options[CURLOPT_CONNECT_TO] ?? null, $url);
        }

        // Operator URLs are left to normal name resolution.
        $options = [];
        $method->invokeArgs(null, ['http://127.0.0.1/file.json', &$options, true]);
        $this->assertArrayNotHasKey(CURLOPT_CONNECT_TO, $options);
    }

    public function testUrlExist()
    {
        $this->assertTrue(UrlFetchTestFileUtilities::url_exist(static::$origin . '/hop/2'));
        $this->assertFalse(UrlFetchTestFileUtilities::url_exist(static::$origin . '/missing'));
        $this->assertFalse(UrlFetchTestFileUtilities::url_exist(static::$origin . '/hop/6'));
        $this->assertFalse(
            UrlFetchTestFileUtilities::url_exist(static::$origin . '/go?to=' . rawurlencode(static::$other . '/'))
        );
        $this->assertFalse(FileUtilities::url_exist(static::$other . '/file.json'));
        $this->assertTrue(FileUtilities::url_exist(static::$other . '/file.json', true));
    }
}
