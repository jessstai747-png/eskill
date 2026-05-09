<?php
declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\ApiRouteScopeResolver;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Security\ApiRouteScopeResolver
 */
class ApiRouteScopeResolverTest extends TestCase
{
    private ApiRouteScopeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ApiRouteScopeResolver();
    }

    public function testSecurityReadRouteRequiresReadOrAdminScope(): void
    {
        $scopes = $this->resolver->resolveRequiredScopes('GET', '/api/security/events');

        $this->assertSame(['admin', 'security:read'], $scopes);
    }

    public function testSecurityMutationRequiresManageOrAdminScope(): void
    {
        $scopes = $this->resolver->resolveRequiredScopes('POST', '/api/security/block-ip');

        $this->assertSame(['admin', 'security:manage'], $scopes);
    }

    public function testBrandSearchStartRequiresWriteScope(): void
    {
        $scopes = $this->resolver->resolveRequiredScopes('POST', '/api/brand-search/start');

        $this->assertSame(['admin', 'brand-search:write'], $scopes);
    }

    public function testBrandSearchReadRoutesRequireReadScope(): void
    {
        $scopes = $this->resolver->resolveRequiredScopes('GET', '/api/brand-search/10/progress');

        $this->assertSame(['admin', 'brand-search:read'], $scopes);
    }

    public function testUnknownRoutesDoNotAddExtraScopes(): void
    {
        $scopes = $this->resolver->resolveRequiredScopes('GET', '/api/orders');

        $this->assertSame([], $scopes);
    }
}
