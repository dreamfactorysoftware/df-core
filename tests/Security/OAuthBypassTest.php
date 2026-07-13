<?php

namespace DreamFactory\Core\Tests\Security;

use DreamFactory\Core\Enums\ServiceTypeGroups;
use DreamFactory\Core\Enums\Verbs;
use DreamFactory\Core\Facades\ServiceManager;
use DreamFactory\Core\Http\Middleware\AccessCheck;
use DreamFactory\Core\Testing\TestCase;

/**
 * Tests that the OAuth bypass in AccessCheck only exempts services that are
 * GENUINELY OAuth/SSO types.
 *
 * Services that run their own OAuth/SSO provider flows must bypass DreamFactory's
 * access check (the callback arrives before the user has a session). Previously
 * the bypass matched on the "_oauth" name suffix ALONE, which let ANY service
 * named *_oauth (e.g. a database service, or an API Builder API whose URL
 * segment ended in "_oauth") skip authentication entirely. The check now also
 * requires the service's type group to be OAuth or SSO.
 *
 * Extends the framework TestCase so the application (and the ServiceManager
 * facade) is booted — isOAuthCallback resolves the \ServiceManager facade, so
 * without a booted app the facade alias would not exist and every lookup would
 * fall into the catch block. stage() is a no-op: this is a pure logic test and
 * needs no migrations or seed data.
 */
class OAuthBypassTest extends TestCase
{
    /** @var AccessCheck */
    private $middleware;

    /** No DB needed for this unit test. */
    public function stage()
    {
        // intentionally empty — skip migrate/seed
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->middleware = new AccessCheck();
    }

    public function tearDown(): void
    {
        ServiceManager::clearResolvedInstances();
        parent::tearDown();
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

    /**
     * Swap a ServiceManager so any service name resolves to the given type group.
     * Pass null to simulate a service/type that does not exist.
     */
    private function fakeServiceGroup(?string $group): void
    {
        if ($group === null) {
            ServiceManager::shouldReceive('getServiceTypeByName')->andReturn(null);
            return;
        }

        ServiceManager::shouldReceive('getServiceTypeByName')->andReturn('mock_type');
        $type = \Mockery::mock();
        $type->shouldReceive('getGroup')->andReturn($group);
        ServiceManager::shouldReceive('getServiceType')->andReturn($type);
    }

    // === Should ALLOW: genuine OAuth/SSO services named *_oauth ===

    public function testAllowsRealOAuthServiceAnyMethodOrResource(): void
    {
        $this->fakeServiceGroup(ServiceTypeGroups::OAUTH);

        $this->assertTrue($this->isOAuthCallback('github_oauth', Verbs::GET, 'sso'));
        $this->assertTrue($this->isOAuthCallback('github_oauth', Verbs::POST, 'callback'));
        $this->assertTrue($this->isOAuthCallback('google_oauth', Verbs::GET, 'anything'));
        $this->assertTrue($this->isOAuthCallback('azure_oauth', Verbs::GET, ''));
    }

    public function testAllowsRealSsoService(): void
    {
        $this->fakeServiceGroup(ServiceTypeGroups::SSO);

        $this->assertTrue($this->isOAuthCallback('company_oauth', Verbs::GET, 'sso'));
    }

    // === Should DENY: *_oauth name but NOT an OAuth/SSO type (the bypass exploit) ===

    public function testDeniesImpostorOAuthNamedDataService(): void
    {
        $this->fakeServiceGroup(ServiceTypeGroups::DATABASE);

        $this->assertFalse($this->isOAuthCallback('evil_oauth', Verbs::GET, '_table/users'));
    }

    public function testDeniesNonexistentOAuthNamedService(): void
    {
        $this->fakeServiceGroup(null);

        $this->assertFalse($this->isOAuthCallback('ghost_oauth', Verbs::GET, ''));
    }

    // === Should DENY: not even _oauth-suffixed (short-circuits before any lookup) ===

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
