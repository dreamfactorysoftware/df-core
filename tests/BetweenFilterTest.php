<?php

use DreamFactory\Core\Enums\DbLogicalOperators;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * BETWEEN was documented in the filter grammar (and handed to agents in MCP
 * query hints) but the parsers never implemented it; after #172 it was also
 * split apart at its own AND. It now expands to two range comparisons before
 * bare-condition wrapping runs.
 */
class BetweenFilterTest extends \PHPUnit\Framework\TestCase
{
    public static function cases(): array
    {
        return [
            'quoted dates'      => ["applied_date BETWEEN '2026-09-01' AND '2026-09-30'", "(applied_date >= '2026-09-01') AND (applied_date <= '2026-09-30')"],
            'numbers'           => ["valuation BETWEEN 1000 AND 50000", "(valuation >= 1000) AND (valuation <= 50000)"],
            'bare dates'        => ["d BETWEEN 2004-01-01 AND 2004-12-31", "(d >= 2004-01-01) AND (d <= 2004-12-31)"],
            'wrapped'           => ["(valuation BETWEEN 1 AND 5)", "((valuation >= 1) AND (valuation <= 5))"],
            'lowercase'         => ["v between 1 and 5", "(v >= 1) AND (v <= 5)"],
            'NOT BETWEEN'       => ["v NOT BETWEEN 1 AND 5", "(v < 1) OR (v > 5)"],
            'combined bare'     => ["status = 'issued' AND valuation BETWEEN 1 AND 5", "(status = 'issued') AND (valuation >= 1) AND (valuation <= 5)"],
            'combined wrapped'  => ["(status = 'issued') AND (valuation BETWEEN 1 AND 5)", "(status = 'issued') AND ((valuation >= 1) AND (valuation <= 5))"],
            'dotted field'      => ["t.v BETWEEN 1 AND 5", "(t.v >= 1) AND (t.v <= 5)"],
            'word in value'     => ["a = 'between us' OR b = 1", "(a = 'between us') OR (b = 1)"],
            'no between'        => ["a=1 AND b=2", "(a=1) AND (b=2)"],
        ];
    }

    #[DataProvider('cases')]
    public function testBetweenExpands(string $in, string $expected)
    {
        $this->assertSame($expected, DbLogicalOperators::wrapBareConditions($in));
    }

    public function testIdempotent()
    {
        $once = DbLogicalOperators::wrapBareConditions("v BETWEEN 1 AND 5 OR c = 2");
        $this->assertSame($once, DbLogicalOperators::wrapBareConditions($once));
    }
}
