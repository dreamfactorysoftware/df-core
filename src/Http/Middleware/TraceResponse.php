<?php

namespace DreamFactory\Core\Http\Middleware;

use Closure;
use DreamFactory\Core\Utility\TraceId;
use Illuminate\Http\Request;

/**
 * Echoes the platform trace id on every API response so callers (and the
 * next hop in an agent chain) can propagate it. Sits first in df.api.
 */
class TraceResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (is_object($response) && isset($response->headers)) {
            $response->headers->set(TraceId::HEADER, TraceId::get());
        }

        return $response;
    }
}
