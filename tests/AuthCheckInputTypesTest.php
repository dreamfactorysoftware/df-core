<?php

use DreamFactory\Core\Components\InternalServiceRequest;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Http\Middleware\AuthCheck;
use DreamFactory\Core\Models\App;
use DreamFactory\Core\Utility\ServiceRequest;
use DreamFactory\Core\Utility\Session;
use Illuminate\Http\Request;

/**
 * AuthCheck reads the API key and the session token (JWT) from several request
 * sources (query, header, payload). Only non-empty string values are used, the
 * same rule getScriptToken() follows. Any other value, such as an array from
 * ?api_key[]=x or from a JSON body, is treated like an absent value, and the
 * order in which the sources are read is unchanged.
 */
class AuthCheckInputTypesTest extends \DreamFactory\Core\Testing\TestCase
{
    /** Values a JSON body can carry that are not usable as a key or token. */
    protected static $nonStringValues = [['x'], ['a' => 'b'], 123, 0, 1.5, true, false, null, '', '0'];

    protected function jsonRequest($uri, array $body, array $server = [], $method = 'POST')
    {
        return Request::create(
            $uri,
            $method,
            [],
            [],
            [],
            array_merge(['CONTENT_TYPE' => 'application/json'], $server),
            json_encode($body)
        );
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

    public function testArrayApiKeyInQueryIsIgnored()
    {
        $this->assertNull(AuthCheck::getApiKey(Request::create('/api/v2/system/environment?api_key[]=x', 'GET')));
        $this->assertNull(AuthCheck::getApiKey(Request::create('/api/v2/system/environment?api_key[a]=x', 'GET')));
    }

    public function testNonStringApiKeyInJsonBodyIsIgnored()
    {
        foreach (static::$nonStringValues as $value) {
            $request = $this->jsonRequest('/api/v2/system/environment', ['api_key' => $value]);
            $this->assertNull(AuthCheck::getApiKey($request), 'api_key=' . json_encode($value));
        }
    }

    public function testApiKeyPrecedenceSkipsNonStringValues()
    {
        // An array in the query does not hide the header value.
        $request = $this->jsonRequest(
            '/api/v2/system/environment?api_key[]=x',
            ['api_key' => 'frombody'],
            ['HTTP_X_DREAMFACTORY_API_KEY' => 'fromheader']
        );
        $this->assertSame('fromheader', AuthCheck::getApiKey($request));

        // ...nor the payload value when there is no header.
        $request = $this->jsonRequest('/api/v2/system/environment?api_key[]=x', ['api_key' => 'frombody']);
        $this->assertSame('frombody', AuthCheck::getApiKey($request));

        // Query > header > payload is unchanged for string values.
        $request = $this->jsonRequest(
            '/api/v2/system/environment?api_key=fromquery',
            ['api_key' => 'frombody'],
            ['HTTP_X_DREAMFACTORY_API_KEY' => 'fromheader']
        );
        $this->assertSame('fromquery', AuthCheck::getApiKey($request));

        $request = $this->jsonRequest(
            '/api/v2/system/environment',
            ['api_key' => 'frombody'],
            ['HTTP_X_DREAMFACTORY_API_KEY' => 'fromheader']
        );
        $this->assertSame('fromheader', AuthCheck::getApiKey($request));

        $request = $this->jsonRequest('/api/v2/system/environment', ['api_key' => 'frombody']);
        $this->assertSame('frombody', AuthCheck::getApiKey($request));
    }

    public function testApiKeyIsNotReadFromSystemAppPayload()
    {
        // system/app payloads carry app records, so their api_key is not a credential.
        $request = $this->jsonRequest('/api/v2/system/app', ['api_key' => 'frombody']);
        $this->assertNull(AuthCheck::getApiKey($request));

        $request = $this->jsonRequest('/api/v2/system/app?api_key=fromquery', ['api_key' => 'frombody']);
        $this->assertSame('fromquery', AuthCheck::getApiKey($request));
    }

    public function testArraySessionTokenInQueryIsIgnored()
    {
        $this->assertNull(AuthCheck::getJwt(Request::create('/api/v2/system/environment?session_token[]=x', 'GET')));
        $this->assertNull(AuthCheck::getJwt(Request::create('/api/v2/system/environment?session_token[a]=x', 'GET')));
    }

    public function testNonStringSessionTokenInJsonBodyIsIgnored()
    {
        foreach (static::$nonStringValues as $value) {
            $request = $this->jsonRequest('/api/v2/system/environment', ['session_token' => $value]);
            $this->assertNull(AuthCheck::getJwt($request), 'session_token=' . json_encode($value));
        }
    }

    public function testSessionTokenPrecedenceSkipsNonStringValues()
    {
        // An array in the input does not hide the header value.
        $request = Request::create(
            '/api/v2/system/environment?session_token[]=x',
            'GET',
            [],
            [],
            [],
            ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => 'fromheader']
        );
        $this->assertSame('fromheader', AuthCheck::getJwt($request));

        $request = $this->jsonRequest(
            '/api/v2/system/environment',
            ['session_token' => ['x']],
            ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => 'fromheader']
        );
        $this->assertSame('fromheader', AuthCheck::getJwt($request));

        // Header > input is unchanged for string values.
        $request = $this->jsonRequest(
            '/api/v2/system/environment',
            ['session_token' => 'frombody'],
            ['HTTP_X_DREAMFACTORY_SESSION_TOKEN' => 'fromheader']
        );
        $this->assertSame('fromheader', AuthCheck::getJwt($request));

        $request = $this->jsonRequest('/api/v2/system/environment', ['session_token' => 'frombody']);
        $this->assertSame('frombody', AuthCheck::getJwt($request));

        $request = Request::create('/api/v2/system/environment?session_token=fromquery', 'GET');
        $this->assertSame('fromquery', AuthCheck::getJwt($request));
    }

    public function testMiddlewareTreatsNonStringValuesAsAbsent()
    {
        $requests = [
            Request::create('/api/v2/system/environment?api_key[]=x', 'GET'),
            Request::create('/api/v2/system/environment?api_key[a]=x', 'GET'),
            Request::create('/api/v2/system/environment?session_token[]=x', 'GET'),
            Request::create('/api/v2/system/environment?api_key[]=x&session_token[]=y', 'GET'),
            $this->jsonRequest('/api/v2/system/environment', ['api_key' => ['x'], 'session_token' => ['y']], [], 'GET'),
            $this->jsonRequest('/api/v2/system/environment', ['api_key' => 123, 'session_token' => true], [], 'GET'),
        ];

        foreach ($requests as $request) {
            $label = $request->getRequestUri() . ' ' . $request->getContent();
            $this->assertSame('passthrough', $this->runMiddleware($request), $label);
            $this->assertNull(Session::getApiKey(), $label);
            $this->assertNull(Session::getSessionToken(), $label);
        }
    }

    public function testMiddlewareStillUsesStringApiKey()
    {
        $apiKey = App::find(1)->api_key;

        $request = Request::create('/api/v2/system/environment?api_key[]=x', 'GET', [], [], [], [
            'HTTP_X_DREAMFACTORY_API_KEY' => $apiKey,
        ]);
        $this->assertSame('passthrough', $this->runMiddleware($request));
        $this->assertSame($apiKey, Session::getApiKey());
    }

    public function testAppIdLookupAcceptsOnlyStringKeys()
    {
        foreach (static::$nonStringValues as $value) {
            $this->assertNull(App::getAppIdByApiKey($value), 'api_key=' . json_encode($value));
        }

        $app = App::find(1);
        $this->assertSame($app->id, App::getAppIdByApiKey($app->api_key));
    }

    public function testInternalServiceRequestUsesOnlyStringApiKeys()
    {
        $request = new InternalServiceRequest();
        $request->setParameters(['api_key' => ['x']]);
        $request->setHeaders(['X_DREAMFACTORY_API_KEY' => 'fromheader']);
        $this->assertSame('fromheader', $request->getApiKey());

        $request->setParameters(['api_key' => 'fromparam']);
        $this->assertSame('fromparam', $request->getApiKey());

        foreach (static::$nonStringValues as $value) {
            $request->setParameters(['api_key' => $value]);
            $request->setHeaders(['X_DREAMFACTORY_API_KEY' => $value]);
            $this->assertNull($request->getApiKey(), 'api_key=' . json_encode($value));
        }
    }

    public function testServiceRequestUsesOnlyStringApiKeys()
    {
        try {
            \Request::swap(Request::create('/api/v2/user/session?api_key[]=x', 'POST', [], [], [], [
                'HTTP_X_DREAMFACTORY_API_KEY' => 'fromheader',
            ]));
            $this->assertSame('fromheader', (new ServiceRequest())->getApiKey());

            \Request::swap($this->jsonRequest('/api/v2/user/session', ['api_key' => ['x']]));
            $this->assertNull((new ServiceRequest())->getApiKey());

            \Request::swap($this->jsonRequest('/api/v2/user/session', ['api_key' => 123]));
            $this->assertNull((new ServiceRequest())->getApiKey());

            \Request::swap($this->jsonRequest('/api/v2/user/session', ['api_key' => 'frombody']));
            $this->assertSame('frombody', (new ServiceRequest())->getApiKey());
        } finally {
            \Request::swap(Request::create('/', 'GET'));
        }
    }

    public function testHttpCallsWithNonStringValuesMatchAbsentValues()
    {
        $expected = $this->call(Verbs::GET, '/api/v2/system/environment')->getStatusCode();

        foreach ([
                     '/api/v2/system/environment?api_key[]=x',
                     '/api/v2/system/environment?session_token[]=x',
                     '/api/v2/system/environment?api_key[a]=x&session_token[b]=y',
                 ] as $uri) {
            $this->assertSame($expected, $this->call(Verbs::GET, $uri)->getStatusCode(), $uri);
        }

        $response = $this->call(
            Verbs::GET,
            '/api/v2/system/environment',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['api_key' => ['x'], 'session_token' => ['y']])
        );
        $this->assertSame($expected, $response->getStatusCode());
    }
}
