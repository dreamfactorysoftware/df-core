<?php

namespace DreamFactory\Core\Tests\Security;

use DreamFactory\Core\Http\Middleware\AccessCheck;
use DreamFactory\Core\Enums\Verbs;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the OAuth callback bypass in AccessCheck is properly scoped.
 *
 * The bypass should only allow GET requests to _oauth-suffixed services
 * on specific callback resources, not blanket access to the entire service.
 */
class OAuthBypassTest extends TestCase
{
    private AccessCheck $middleware;

    public function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AccessCheck();
    }

    /**
     * Call the private isOAuthCallback method via reflection.
     */
    private function isOAuthCallback(string $service, string $method, string $component): bool
    {
        $reflection = new \ReflectionMethod(AccessCheck::class, 'isOAuthCallback');
        $reflection->setAccessible(true);
        return $reflection->invoke($this->middleware, $service, $method, $component);
    }

    // === Should ALLOW (legitimate OAuth callbacks) ===

    public function testAllowsGetToOAuthSsoCallback(): void
    {
        $this->assertTrue($this->isOAuthCallback('github_oauth', Verbs::GET, 'sso'));
    }

    public function testAllowsGetToOAuthCallbackResource(): void
    {
        $this->assertTrue($this->isOAuthCallback('google_oauth', Verbs::GET, 'callback'));
    }

    public function testAllowsGetToOAuthRootResource(): void
    {
        $this->assertTrue($this->isOAuthCallback('azure_oauth', Verbs::GET, ''));
    }

    // === Should DENY (non-callback or non-GET requests) ===

    public function testDeniesPostToOAuthService(): void
    {
        $this->assertFalse($this->isOAuthCallback('github_oauth', Verbs::POST, 'sso'));
    }

    public function testDeniesPutToOAuthService(): void
    {
        $this->assertFalse($this->isOAuthCallback('github_oauth', Verbs::PUT, ''));
    }

    public function testDeniesDeleteToOAuthService(): void
    {
        $this->assertFalse($this->isOAuthCallback('github_oauth', Verbs::DELETE, 'sso'));
    }

    public function testDeniesGetToArbitraryResourceOnOAuthService(): void
    {
        $this->assertFalse($this->isOAuthCallback('github_oauth', Verbs::GET, 'admin'));
    }

    public function testDeniesGetToNonOAuthService(): void
    {
        $this->assertFalse($this->isOAuthCallback('github', Verbs::GET, 'sso'));
    }

    public function testDeniesGetToServiceWithOAuthInMiddle(): void
    {
        $this->assertFalse($this->isOAuthCallback('my_oauth_service', Verbs::GET, 'sso'));
    }

    /**
     * Regression: previously any service ending in _oauth bypassed ALL access checks
     * for ANY method and ANY resource path. This test ensures that attack surface is closed.
     */
    public function testDeniesPostDataModificationOnOAuthService(): void
    {
        $this->assertFalse($this->isOAuthCallback('evil_oauth', Verbs::POST, 'users'));
        $this->assertFalse($this->isOAuthCallback('evil_oauth', Verbs::PATCH, 'config'));
        $this->assertFalse($this->isOAuthCallback('evil_oauth', Verbs::DELETE, 'data'));
    }
}
