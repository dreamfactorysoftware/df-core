<?php

namespace DreamFactory\Core\Utility;

use Illuminate\Support\Str;

/**
 * Platform-wide trace id: one id per inbound request, propagated across
 * chat -> MCP -> REST hops via the X-DreamFactory-Trace-Id header, so every
 * audit row produced by one agent action (ai_usage_log, ai_prompt_log,
 * mcp_request_log, ...) can be joined into a single trace.
 */
class TraceId
{
    public const HEADER = 'X-DreamFactory-Trace-Id';

    private static ?string $id = null;

    public static function get(): string
    {
        if (self::$id === null) {
            $inbound = request()?->header(self::HEADER);
            self::$id = self::isValid($inbound) ? $inbound : (string) Str::uuid();
        }

        return self::$id;
    }

    /**
     * Accept a sane external id (agents, gateways, retries of parked tasks)
     * without letting a caller inject junk into audit rows.
     */
    private static function isValid(?string $id): bool
    {
        return is_string($id) && preg_match('/^[A-Za-z0-9._-]{8,64}$/', $id) === 1;
    }

    /** Testing/long-running-worker hook; a normal PHP-FPM request never needs it. */
    public static function reset(): void
    {
        self::$id = null;
    }
}
