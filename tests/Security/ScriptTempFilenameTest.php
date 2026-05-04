<?php

namespace DreamFactory\Core\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: PhpExecutable trait must use cryptographically random temp
 * filenames AND escape the filename when interpolating into a shell command.
 *
 * The April 2026 audit (df-script F-06) flagged:
 *
 *     $storage_location = storage_path() . DIRECTORY_SEPARATOR
 *                       . uniqid($this->commandName . "_", true);
 *     ...
 *     $runnerShell .= ' ' . $storage_location;
 *
 * Two problems:
 *   1. uniqid() is microtime-derived; filenames are predictable, enabling
 *      a local race that reads the script file (containing injected
 *      session tokens / API keys) before exec consumes it.
 *   2. The path is concatenated into the shell string without
 *      escapeshellarg(); a path with whitespace or shell metacharacters
 *      (Windows / unusual storage_path config) becomes shell injection.
 *
 * After the fix:
 *   1. Temp filenames use bin2hex(random_bytes(16)) — 128 bits of CSPRNG
 *      entropy.
 *   2. The file path is escapeshellarg()'d before appending to the shell
 *      command, matching the inline path's existing behavior.
 */
class ScriptTempFilenameTest extends TestCase
{
    private string $sourcePath;
    private string $contents;

    protected function setUp(): void
    {
        $this->sourcePath = __DIR__ . '/../../src/Components/PhpExecutable.php';
        $this->assertFileExists($this->sourcePath);
        $this->contents = file_get_contents($this->sourcePath);
    }

    public function testTempFilenameIsRandomBytes(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/uniqid\s*\(\s*\$this->commandName/',
            $this->contents,
            'PhpExecutable must not use uniqid() for temp script filenames; '
            . 'use bin2hex(random_bytes(16)) for cryptographic randomness.'
        );
        $this->assertMatchesRegularExpression(
            '/bin2hex\s*\(\s*random_bytes\s*\(/',
            $this->contents,
            'PhpExecutable must use bin2hex(random_bytes(...)) for temp filenames.'
        );
    }

    public function testFilePathIsShellEscaped(): void
    {
        // Locate the buildCommand() method body and check that any append
        // of $storage_location to $runnerShell uses escapeshellarg().
        $this->assertMatchesRegularExpression(
            '/\$runnerShell\s*\.=\s*[\'"]\s\s*[\'"]\s*\.\s*escapeshellarg\s*\(\s*\$storage_location\s*\)/',
            $this->contents,
            'PhpExecutable must escapeshellarg($storage_location) when appending to the shell command'
        );
    }
}
