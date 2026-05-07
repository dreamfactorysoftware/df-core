<?php

use DreamFactory\Core\Services\DfCorsService;
use Symfony\Component\HttpFoundation\Request;

class DfCorsServiceTest extends \DreamFactory\Core\Testing\TestCase
{
    public function testPreflightAllowsConfiguredMethod()
    {
        $service = new DfCorsService([
            'allowedOrigins' => ['*'],
            'allowedHeaders' => ['*'],
            'allowedMethods' => ['GET', 'POST'],
        ]);

        $response = $service->handlePreflightRequest($this->makePreflightRequest('POST'));

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testPreflightRejectsUnconfiguredMethod()
    {
        $service = new DfCorsService([
            'allowedOrigins' => ['*'],
            'allowedHeaders' => ['*'],
            'allowedMethods' => ['GET'],
        ]);

        $response = $service->handlePreflightRequest($this->makePreflightRequest('POST'));

        $this->assertEquals(405, $response->getStatusCode());
    }

    public function testPreflightAllowsWildcardMethodFromLaravelConfig()
    {
        $service = new DfCorsService([
            'allowed_origins' => ['*'],
            'allowed_headers' => ['*'],
            'allowed_methods' => ['*'],
        ]);

        $response = $service->handlePreflightRequest($this->makePreflightRequest('PATCH'));

        $this->assertEquals(204, $response->getStatusCode());
    }

    public function testPreflightNormalizesConfiguredMethods()
    {
        $service = new DfCorsService([
            'allowedOrigins' => ['*'],
            'allowedHeaders' => ['*'],
            'allowedMethods' => [' post '],
        ]);

        $response = $service->handlePreflightRequest($this->makePreflightRequest('POST'));

        $this->assertEquals(204, $response->getStatusCode());
    }

    protected function makePreflightRequest(string $method): Request
    {
        return Request::create('/api/v2/system/environment', 'OPTIONS', [], [], [], [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => $method,
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-dreamfactory-api-key,content-type',
        ]);
    }
}
