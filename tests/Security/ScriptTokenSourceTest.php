<?php

namespace DreamFactory\Core\Tests\Security;

use DreamFactory\Core\Http\Middleware\AuthCheck;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Security: script_token must only be accepted via the dedicated header.
 *
 * The April 2026 audit (df-script F-08) found that AuthCheck::getScriptToken()
 * accepted the script token from three places: the `X-DreamFactory-Script-Token`
 * header, the `script_token` query parameter, and the `script_token` body field.
 *
 * The query parameter and body fallbacks expose the token to logs, browser
 * history, Referer headers, and CDN caches. Worse, any URL or form a user is
 * tricked into loading with `?script_token=...` would authenticate as the
 * running script's session for its 300-second TTL.
 *
 * After the fix, only the header source is honored.
 */
class ScriptTokenSourceTest extends TestCase
{
    public function testHeaderTokenIsReturned(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('X-DreamFactory-Script-Token', 'header-tok-abc123');

        $token = AuthCheck::getScriptToken($request);

        $this->assertSame('header-tok-abc123', $token,
            'Script token must be accepted from the dedicated header');
    }

    public function testQueryStringTokenIsRejected(): void
    {
        $request = Request::create('/?script_token=qs-leaked-token', 'GET');

        $token = AuthCheck::getScriptToken($request);

        $this->assertEmpty($token,
            'Script token MUST NOT be accepted from the query string '
            . '(token would leak via access logs, browser history, Referer)'
        );
    }

    public function testRequestBodyTokenIsRejected(): void
    {
        $request = Request::create('/', 'POST', ['script_token' => 'body-leaked-token']);

        $token = AuthCheck::getScriptToken($request);

        $this->assertEmpty($token,
            'Script token MUST NOT be accepted from request body '
            . '(token would be visible in audit logs and CSRF amplification surface)'
        );
    }

    public function testEmptyRequestReturnsEmpty(): void
    {
        $request = Request::create('/', 'GET');

        $token = AuthCheck::getScriptToken($request);

        $this->assertEmpty($token, 'A request with no token sources must return empty');
    }
}
