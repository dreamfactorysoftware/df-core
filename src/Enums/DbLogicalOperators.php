<?php
namespace DreamFactory\Core\Enums;


/**
 * DbLogicalOperators
 * DB server-side filter logical operator string constants
 */
class DbLogicalOperators extends FactoryEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string
     */
    const AND_SYM = '&&';
    const AND_STR = 'AND';
    /**
     * @var string
     */
    const OR_SYM = '||';
    const OR_STR = 'OR';
    /**
     * @var string
     */
    const NOR_STR = 'NOR';
    /**
     * @var string
     */
    const XOR_STR = 'XOR';
    /**
     * @var string
     */
    const NOT_STR = 'NOT';
    /**
     * Rewrite BETWEEN into the two range comparisons the filter parsers understand:
     *   f BETWEEN a AND b       becomes   (f >= a) AND (f <= b)
     *   f NOT BETWEEN a AND b   becomes   (f < a) OR (f > b)
     * BETWEEN was documented (and offered in MCP query hints) but never parsed;
     * it also has to run before wrapBareConditions() splits on its inner AND.
     * Bounds may be quoted strings or bare tokens (numbers, unquoted dates).
     */
    public static function expandBetween(string $filter): string
    {
        if (false === stripos($filter, 'between')) {
            return $filter;
        }
        $bound = "('(?:[^']|'')*'|\"[^\"]*\"|[^\\s()]+)";
        $re = '/([A-Za-z_][\\w.]*)\\s+(NOT\\s+)?BETWEEN\\s+' . $bound . '\\s+AND\\s+' . $bound . '/i';

        return preg_replace_callback($re, function ($m) {
            return empty($m[2])
                ? "({$m[1]} >= {$m[3]}) AND ({$m[1]} <= {$m[4]})"
                : "({$m[1]} < {$m[3]}) OR ({$m[1]} > {$m[4]})";
        }, $filter);
    }

    /**
     * Wrap bare comparison conditions in parentheses so that
     *   a='x' OR b='y'      becomes   (a='x') OR (b='y')
     * and parses exactly like the parenthesized form the filter parsers expect.
     * Without this, everything after the first comparison operator was bound
     * as the literal value and the query quietly matched nothing (#172).
     *
     * Splits only on top-level logical operators: outside quotes and outside
     * parentheses. Already-parenthesized input comes back unchanged.
     */
    public static function wrapBareConditions(string $filter): string
    {
        $filter = static::expandBetween($filter);
        $ops = [self::AND_SYM, self::AND_STR, self::OR_SYM, self::OR_STR, self::NOR_STR, self::XOR_STR];
        $pieces = [];
        $joins = [];
        $depth = 0;
        $quote = null;
        $start = 0;
        $len = strlen($filter);

        for ($i = 0; $i < $len; $i++) {
            $c = $filter[$i];
            if ($quote !== null) {
                if ($c === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($c === "'" || $c === '"') {
                $quote = $c;
                continue;
            }
            if ($c === '(') {
                $depth++;
                continue;
            }
            if ($c === ')') {
                $depth--;
                continue;
            }
            if ($depth !== 0 || !ctype_space($c)) {
                continue;
            }
            foreach ($ops as $op) {
                $opLen = strlen($op);
                $after = $filter[$i + 1 + $opLen] ?? '';
                if (0 === strcasecmp(substr($filter, $i + 1, $opLen), $op) && ($after === '(' || ctype_space($after))) {
                    $pieces[] = trim(substr($filter, $start, $i - $start));
                    $joins[] = strtoupper($op);
                    $i += $opLen; // loop increment steps past the trailing space or onto the '('
                    $start = $i + 1;
                    continue 2;
                }
            }
        }

        if (empty($joins)) {
            return $filter;
        }
        $pieces[] = trim(substr($filter, $start));

        $out = '';
        foreach ($pieces as $k => $piece) {
            if ($piece === '') {
                return $filter; // malformed, let the parser reject it
            }
            if ($piece[0] !== '(' || substr($piece, -1) !== ')') {
                $piece = '(' . $piece . ')';
            }
            $out .= ($k ? ' ' . $joins[$k - 1] . ' ' : '') . $piece;
        }

        return $out;
    }
}
