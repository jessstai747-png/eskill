<?php
declare(strict_types=1);

namespace App\Security;

/**
 * Resolves the minimum Bearer-token scopes required by sensitive API routes.
 *
 * Session-authenticated requests remain governed by the existing web auth flow.
 * This resolver is only used when the request reaches the API gateway with a
 * Bearer token and allows the gateway to keep authorization rules centralized.
 */
final class ApiRouteScopeResolver
{
    /**
     * Returns the accepted scopes for a given method and normalized path.
     *
     * An empty array means the route does not require an explicit extra scope
     * beyond having a valid token.
     *
     * @return array<int,string>
     */
    public function resolveRequiredScopes(string $method, string $path): array
    {
        $normalizedMethod = strtoupper(trim($method));
        $normalizedPath = '/' . ltrim(trim($path), '/');

        if ($this->startsWith($normalizedPath, '/api/security')) {
            return $normalizedMethod === 'GET'
                ? ['admin', 'security:read']
                : ['admin', 'security:manage'];
        }

        if ($normalizedPath === '/api/brand-search/start') {
            return ['admin', 'brand-search:write'];
        }

        if ($this->startsWith($normalizedPath, '/api/brand-search/')) {
            return ['admin', 'brand-search:read'];
        }

        return [];
    }

    /**
     * Performs a strict prefix match while keeping intent readable.
     */
    private function startsWith(string $value, string $prefix): bool
    {
        return strncmp($value, $prefix, strlen($prefix)) === 0;
    }
}
