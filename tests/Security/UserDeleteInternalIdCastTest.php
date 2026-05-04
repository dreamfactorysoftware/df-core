<?php

namespace DreamFactory\Core\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * Security: User::deleteInternal() must integer-cast $id before
 * interpolating it into raw SQL on the SQL Server cleanup path.
 *
 * The April 2026 audit (df-core P1) found that the SQL Server cleanup branch
 * builds raw UPDATE statements with `'] = ' . $id` style concatenation:
 *
 *     $stmt = 'update [' . $reference->refTable . '] set ['
 *           . $reference->refField[0] . '] = null where ['
 *           . $reference->refField[0] . '] = ' . $id;
 *
 * `$id` arrives from the route as a path parameter and Laravel does not
 * coerce it to int. A caller passing `1; DROP TABLE user--` would have the
 * payload concatenated raw into the UPDATE.
 *
 * After the fix, $id is normalized via `(int) $id` before reaching the SQL
 * Server cleanup path, so any non-numeric or stacked-statement payload is
 * silently discarded by PHP's int-cast semantics.
 */
class UserDeleteInternalIdCastTest extends TestCase
{
    private string $sourcePath;
    private string $contents;

    protected function setUp(): void
    {
        $this->sourcePath = __DIR__ . '/../../src/Models/User.php';
        $this->assertFileExists($this->sourcePath);
        $this->contents = file_get_contents($this->sourcePath);
    }

    public function testIdIsIntegerCastBeforeRawSqlPath(): void
    {
        // We require an explicit (int) cast on $id somewhere inside the
        // deleteInternal() method, before the sqlsrv branch runs.
        $methodStart = strpos($this->contents, 'function deleteInternal');
        $this->assertNotFalse($methodStart, 'deleteInternal() must exist');

        $sqlsrvBranchStart = strpos($this->contents, "'sqlsrv'", $methodStart);
        $this->assertNotFalse($sqlsrvBranchStart,
            'sqlsrv driver branch must still exist (cleanup is sqlsrv-specific)'
        );

        $methodPrefix = substr($this->contents, $methodStart, $sqlsrvBranchStart - $methodStart);

        $hasIntCast =
            preg_match('/\(int\)\s*\$id\b/', $methodPrefix) === 1
            || preg_match('/\$id\s*=\s*\(int\)\s*\$id\b/', $methodPrefix) === 1
            || preg_match('/intval\s*\(\s*\$id\s*\)/', $methodPrefix) === 1;

        $this->assertTrue(
            $hasIntCast,
            'deleteInternal() must integer-cast $id before the sqlsrv raw-SQL '
            . 'cleanup branch. Without this, a non-numeric or stacked-statement '
            . 'payload is concatenated directly into the UPDATE.'
        );
    }

    public function testRawSqlNoLongerInterpolatesUntrustedValue(): void
    {
        // Defense-in-depth: even with the cast, the raw SQL pattern is
        // brittle. The fix should keep $id concatenation only AFTER the
        // cast has happened. This test asserts the existence of the
        // pattern AND the proximity of the cast — at minimum the cast
        // must come before any '] = ' . $id concatenation.
        $methodStart = strpos($this->contents, 'function deleteInternal');
        $idCastPos = false;
        if (preg_match('/\$id\s*=\s*\(int\)\s*\$id\b/', $this->contents, $m, PREG_OFFSET_CAPTURE, $methodStart)) {
            $idCastPos = $m[0][1];
        }
        $rawConcatPos = strpos($this->contents, "'] = ' . \n", $methodStart);
        if ($rawConcatPos === false) {
            $rawConcatPos = strpos($this->contents, "'] = ' .\n", $methodStart);
        }
        if ($rawConcatPos === false) {
            // Try the literal multi-line form actually used in the file.
            $rawConcatPos = strpos($this->contents, "'] = '", $methodStart);
        }

        $this->assertNotFalse($idCastPos,
            '$id must be reassigned via (int)$id before any raw SQL concatenation'
        );
        $this->assertNotFalse($rawConcatPos,
            'Test sanity: the raw concat pattern must still be locatable in source'
        );
        $this->assertLessThan(
            $rawConcatPos,
            $idCastPos,
            'The (int) cast must appear BEFORE the raw SQL concatenation'
        );
    }
}
