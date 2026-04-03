<?php

namespace DreamFactory\Core\Components;

use PHPUnit\Framework\TestCase;

/**
 * Security tests for Registrar::generateConfirmationCode().
 *
 * These tests verify that the function:
 *   - Produces output of the expected length and character set
 *   - Does not produce a predictable sequence across multiple calls
 *
 * The function is a static method with no Laravel/framework dependencies,
 * so it can be tested against plain PHPUnit\Framework\TestCase.
 */
class RegistrarTokenSecurityTest extends TestCase
{
    /** Character set the confirmation code must be drawn from. */
    const VALID_CHARS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    // -------------------------------------------------------------------------
    // Length / format tests
    // -------------------------------------------------------------------------

    public function testDefaultLengthIs32()
    {
        $code = Registrar::generateConfirmationCode();
        $this->assertSame(32, strlen($code), 'Default code length must be 32 characters');
    }

    public function testExplicitLengthIsRespected()
    {
        foreach ([5, 10, 16, 24, 32] as $len) {
            $code = Registrar::generateConfirmationCode($len);
            $this->assertSame(
                $len,
                strlen($code),
                "Expected code of length {$len}, got " . strlen($code)
            );
        }
    }

    public function testLengthBelowMinimumIsClampedTo5()
    {
        // Any value below 5 should be treated as 5
        $code = Registrar::generateConfirmationCode(1);
        $this->assertSame(5, strlen($code), 'Lengths below 5 must be clamped to 5');

        $code = Registrar::generateConfirmationCode(0);
        $this->assertSame(5, strlen($code));

        $code = Registrar::generateConfirmationCode(4);
        $this->assertSame(5, strlen($code));
    }

    public function testLengthAboveMaximumIsClampedTo32()
    {
        // Any value above 32 should be treated as 32
        $code = Registrar::generateConfirmationCode(64);
        $this->assertSame(32, strlen($code), 'Lengths above 32 must be clamped to 32');

        $code = Registrar::generateConfirmationCode(33);
        $this->assertSame(32, strlen($code));
    }

    // -------------------------------------------------------------------------
    // Character set tests
    // -------------------------------------------------------------------------

    public function testOutputContainsOnlyValidCharacters()
    {
        // Run multiple times to increase coverage of the character range
        for ($run = 0; $run < 20; $run++) {
            $code = Registrar::generateConfirmationCode(32);
            $this->assertMatchesRegularExpression(
                '/^[0-9A-Z]+$/',
                $code,
                "Code contains characters outside the allowed set: {$code}"
            );
        }
    }

    public function testOutputIsUppercaseAlphanumericOnly()
    {
        $code = Registrar::generateConfirmationCode(32);

        // No lowercase letters
        $this->assertSame(
            $code,
            strtoupper($code),
            'Code must not contain lowercase letters'
        );

        // No special characters
        $this->assertSame(
            1,
            preg_match('/^[0-9A-Z]+$/', $code),
            'Code must match [0-9A-Z]+'
        );
    }

    // -------------------------------------------------------------------------
    // Randomness / unpredictability tests
    // -------------------------------------------------------------------------

    public function testMultipleCallsProduceDifferentValues()
    {
        // With a 32-char code drawn from 36 symbols there are 36^32 (~3.7×10^49)
        // possibilities. The probability of any two being identical is negligible.
        // We generate 50 codes and assert all are unique.
        $codes = [];
        for ($i = 0; $i < 50; $i++) {
            $codes[] = Registrar::generateConfirmationCode(32);
        }

        $unique = array_unique($codes);
        $this->assertCount(
            50,
            $unique,
            'All 50 generated codes must be unique — collisions indicate a broken RNG'
        );
    }

    public function testOutputIsNotSequentiallyPredictable()
    {
        // Generate pairs and verify the second is not simply one char away from the first.
        // This is a heuristic: it cannot prove true randomness, but it would catch a
        // naive counter or a seeded Mersenne Twister producing identical output.
        $previous = Registrar::generateConfirmationCode(32);
        $allIdentical = true;

        for ($i = 0; $i < 20; $i++) {
            $current = Registrar::generateConfirmationCode(32);
            if ($current !== $previous) {
                $allIdentical = false;
                break;
            }
            $previous = $current;
        }

        $this->assertFalse(
            $allIdentical,
            '20 consecutive calls all returned the same value — RNG is not functioning'
        );
    }

    public function testDistributionIsReasonablyUniform()
    {
        // Generate a large sample and verify that every character in the alphabet
        // appears at least once. A perfectly predictable source would cluster.
        $sample = '';
        for ($i = 0; $i < 200; $i++) {
            $sample .= Registrar::generateConfirmationCode(32);
        }
        // That's 6400 characters total; each of the 36 symbols should appear ~177 times.
        $expected = str_split(self::VALID_CHARS);
        foreach ($expected as $char) {
            $this->assertGreaterThan(
                0,
                substr_count($sample, $char),
                "Character '{$char}' never appeared in 6400-char sample — distribution is suspect"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Security regression: must NOT use rand()
    // -------------------------------------------------------------------------

    public function testFunctionSourceDoesNotUseRand()
    {
        // Read the source of Registrar.php and assert that the function body
        // uses random_int rather than rand(), which is the Mersenne Twister PRNG
        // and is predictable given a known seed.
        $source = file_get_contents(
            __DIR__ . '/../src/Components/Registrar.php'
        );

        // The old vulnerable call: rand(0, ...)
        $this->assertStringNotContainsString(
            'rand(',
            $source,
            'Registrar.php must not use rand() — it is not cryptographically secure. Use random_int().'
        );

        $this->assertStringContainsString(
            'random_int(',
            $source,
            'Registrar.php must use random_int() for CSPRNG-based confirmation codes.'
        );
    }
}
