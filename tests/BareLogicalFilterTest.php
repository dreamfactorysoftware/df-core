<?php

use DreamFactory\Core\Enums\DbLogicalOperators;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression test for #172. Filters written as a='x' OR b='y' (no parentheses)
 * must parse like (a='x') OR (b='y') instead of binding "x' OR b='y" as the
 * value and returning zero rows.
 */
class BareLogicalFilterTest extends \PHPUnit\Framework\TestCase
{
    public static function cases(): array
    {
        return [
            'bare OR'              => ["a='x' OR b='y'", "(a='x') OR (b='y')"],
            'bare AND'             => ["a='x' AND b=3", "(a='x') AND (b=3)"],
            'lowercase op'         => ["a='x' or b='y'", "(a='x') OR (b='y')"],
            'three terms'          => ["a=1 AND b=2 OR c=3", "(a=1) AND (b=2) OR (c=3)"],
            'mixed wrapped/bare'   => ["(a=1) AND b=2", "(a=1) AND (b=2)"],
            'symbol ops'           => ["a=1 && b=2 || c=3", "(a=1) && (b=2) || (c=3)"],
            'op inside quotes'     => ["a='Rock AND Roll' OR b='y'", "(a='Rock AND Roll') OR (b='y')"],
            'op inside dquotes'    => ['a="x OR y"', 'a="x OR y"'],
            'value list with op'   => ["id in (1,2) OR c='z'", "(id in (1,2)) OR (c='z')"],
            'op inside parens'     => ["(a=1 OR b=2)", "(a=1 OR b=2)"],
            'op followed by paren' => ["a=1 AND(b=2)", "(a=1) AND (b=2)"],
            'already wrapped'      => ["(a=1) AND (b=2)", "(a=1) AND (b=2)"],
            'no logical op'        => ["a like 'x%'", "a like 'x%'"],
            'field named andy'     => ["andy=1", "andy=1"],
            'NOT prefix'           => ["NOT (a=1) AND b=2", "(NOT (a=1)) AND (b=2)"],
            'trailing op is left alone' => ["a=1 AND ", "a=1 AND "],
        ];
    }

    #[DataProvider('cases')]
    public function testWrapBareConditions(string $in, string $expected)
    {
        $this->assertSame($expected, DbLogicalOperators::wrapBareConditions($in));
    }

    public function testIdempotent()
    {
        $once = DbLogicalOperators::wrapBareConditions("a='x' OR b='y' AND c=1");
        $this->assertSame($once, DbLogicalOperators::wrapBareConditions($once));
    }
}
