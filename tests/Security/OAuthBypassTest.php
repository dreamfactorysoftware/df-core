<?php

namespace DreamFactory\Core\Tests\Security;

use DreamFactory\Core\Http\Middleware\AccessCheck;
use DreamFactory\Core\Enums\Verbs;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the OAuth bypass in AccessCheck correctly identifies _oauth services.
 *
 * Services ending in _oauth handle their own authentication (OAuth provider flows)
 * and must bypass DreamFactory's access checks for all methods and resources.
 * Non-_oauth services must never be bypassed.
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

    // === Should ALLOW (_oauth-suffixed services) ===

    public function testAllowsOAuthServiceGet(): void
    {
        $this->assertTrue($this->isOAuthCallback('github_oauth', Verbs::GET, 'sso'));
    }

    public function testAllowsOAuthServicePost(): void
    {
        $this->assertTrue($this->isOAuthCallback('github_oauth', Verbs::POST, 'callback'));
    }

    public function testAllowsOAuthServiceAnyResource(): void
    {
        $this->assertTrue($this->isOAuthCallback('google_oauth', Verbs::GET, 'anything'));
    }

    public function testAllowsOAuthServiceRoot(): void
    {
        $this->assertTrue($this->isOAuthCallback('azure_oauth', Verbs::GET, ''));
    }

    // === Should DENY (non-_oauth services) ===

    public function testDeniesNonOAuthService(): void
    {
        $this->assertFalse($this->isOAuthCallback('github', Verbs::GET, 'sso'));
    }

    public function testDeniesServiceWithOAuthInMiddle(): void
    {
        $this->assertFalse($this->isOAuthCallback('my_oauth_service', Verbs::GET, 'sso'));
    }

    public function testDeniesRegularService(): void
    {
        $this->assertFalse($this->isOAuthCallback('db', Verbs::GET, ''));
    }

    public function testDeniesSystemService(): void
    {
        $this->assertFalse($this->isOAuthCallback('system', Verbs::POST, 'user'));
    }
}
