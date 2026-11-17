<?php

use DreamFactory\Core\Enums\ServiceRequestorTypes;
use DreamFactory\Core\Http\Middleware\AuthCheck;
use DreamFactory\Core\Utility\Session;
use Illuminate\Http\Request;

/**
 * A script_token must be validated against the cache BEFORE the
 * request's requestor type is switched to SCRIPT, regardless of whether a
 * JWT is present. An attacker with a normal session who appends
 * &script_token=<anything> must NOT be able to elevate to the SCRIPT
 * requestor and pick up role grants scoped to SCRIPT.
 */
class AuthCheckScriptTokenTest extends \DreamFactory\Core\Testing\TestCase
{
    protected function resetRequestor()
    {
        // Default requestor is API.
        Session::setRequestor(ServiceRequestorTypes::API);
    }

    protected function runMiddleware(Request $request)
    {
        $middleware = new AuthCheck();

        // AuthCheck calls ini_set('session.use_cookies') first. The base TestCase
        // has already echoed a banner by then, so PHP raises a warning and
        // Laravel turns it into an ErrorException. Silence that one call.
        return @$middleware->handle($request, function ($req) {
            return 'passthrough';
        });
    }

    /**
     * Invalid (uncached) script_token must leave the requestor at API,
     * even when a session_token (JWT) is also supplied on the request.
     */
    public function testInvalidScriptTokenDoesNotElevateToScript()
    {
        $this->resetRequestor();
        Cache::forget('script-token:anythinggoes');

        $request = Request::create(
            '/api/v2/samples_mysql/_table/products',
            'GET',
            ['script_token' => 'anythinggoes', 'session_token' => 'a.bogus.jwt']
        );

        $this->runMiddleware($request);

        $this->assertEquals(
            ServiceRequestorTypes::API,
            Session::getRequestor(),
            'An unvalidated script_token must NOT switch the requestor to SCRIPT.'
        );
    }

    /**
     * A script_token present in the cache is the legitimate scripting path.
     * It must still set the requestor to SCRIPT exactly as before.
     */
    public function testValidScriptTokenStillElevatesToScript()
    {
        $this->resetRequestor();
        Cache::put('script-token:validtoken', ['app_id' => 1, 'user_id' => 1], 60);

        $request = Request::create(
            '/api/v2/samples_mysql/_table/products',
            'GET',
            ['script_token' => 'validtoken']
        );

        $this->runMiddleware($request);

        $this->assertEquals(
            ServiceRequestorTypes::SCRIPT,
            Session::getRequestor(),
            'A valid (cached) script_token must set the requestor to SCRIPT.'
        );

        Cache::forget('script-token:validtoken');
    }

    /**
     * No script_token at all: requestor stays API.
     */
    public function testNoScriptTokenKeepsApiRequestor()
    {
        $this->resetRequestor();

        $request = Request::create('/api/v2/samples_mysql/_table/products', 'GET');

        $this->runMiddleware($request);

        $this->assertEquals(ServiceRequestorTypes::API, Session::getRequestor());
    }
}
